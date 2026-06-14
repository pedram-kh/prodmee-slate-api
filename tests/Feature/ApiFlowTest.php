<?php

namespace Tests\Feature;

use App\Models\LoginCode;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApiFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $status = 'active'): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => $role . '.' . uniqid() . '@prodmee.test',
            'role' => $role,
            'status' => $status,
            'email_verified_at' => now(),
        ]);
    }

    public function test_otp_request_is_invite_gated_and_generic(): void
    {
        Mail::fake();
        $user = $this->makeUser('member');

        // Unknown email: still 200, but no code created / no mail.
        $this->postJson('/api/auth/request-code', ['email' => 'nobody@nowhere.test'])
            ->assertOk();
        $this->assertDatabaseCount('login_codes', 0);
        Mail::assertNothingSent();

        // Known email: code created + mail sent.
        $this->postJson('/api/auth/request-code', ['email' => $user->email])->assertOk();
        $this->assertDatabaseCount('login_codes', 1);
        Mail::assertSent(\App\Mail\LoginCodeMail::class);
    }

    public function test_full_otp_login_issues_token(): void
    {
        Mail::fake();
        $user = $this->makeUser('member', 'invited');

        $this->postJson('/api/auth/request-code', ['email' => $user->email])->assertOk();

        // Capture the raw code via the mailable.
        $code = null;
        Mail::assertSent(\App\Mail\LoginCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;
            return true;
        });

        $resp = $this->postJson('/api/auth/verify-code', ['email' => $user->email, 'code' => $code])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);

        $this->assertSame('active', $user->fresh()->status);
        $this->assertNotEmpty($resp->json('token'));
    }

    public function test_wrong_code_is_rejected(): void
    {
        $user = $this->makeUser('member');
        LoginCode::create([
            'email' => $user->email,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/auth/verify-code', ['email' => $user->email, 'code' => '000000'])
            ->assertStatus(422);
    }

    public function test_member_only_sees_assigned_projects(): void
    {
        $admin = $this->makeUser('admin');
        $member = $this->makeUser('member');

        $assigned = Project::create(['title' => 'Assigned', 'stage' => 'idea']);
        $assigned->users()->attach($member->id, ['relation' => 'member']);
        Project::create(['title' => 'Hidden', 'stage' => 'idea']);

        // Member sees only the assigned one.
        $this->actingAs($member, 'sanctum')->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Assigned']);

        // Admin sees both.
        $this->actingAs($admin, 'sanctum')->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_external_cannot_access_admin_settings(): void
    {
        $external = $this->makeUser('external');
        $this->actingAs($external, 'sanctum')->getJson('/api/settings/users')->assertStatus(403);
    }

    public function test_admin_can_move_project_stage(): void
    {
        $admin = $this->makeUser('admin');
        $project = Project::create(['title' => 'Mover', 'stage' => 'idea']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/projects/{$project->id}/stage", ['stage' => 'desarrollo'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'desarrollo');
    }

    public function test_file_metadata_store_and_cover_replacement(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        $admin = $this->makeUser('admin');
        $project = Project::create(['title' => 'Files', 'stage' => 'idea']);

        // First cover.
        $this->actingAs($admin, 'sanctum')->postJson("/api/projects/{$project->id}/files", [
            'slot' => 'cover', 'key' => 'projects/1/cover/a.jpg', 'name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 1000,
        ])->assertCreated();
        $this->assertSame('projects/1/cover/a.jpg', $project->fresh()->cover_key);

        // Second cover replaces the first (only one cover row remains).
        $this->actingAs($admin, 'sanctum')->postJson("/api/projects/{$project->id}/files", [
            'slot' => 'cover', 'key' => 'projects/1/cover/b.jpg', 'name' => 'b.jpg', 'mime_type' => 'image/jpeg', 'size' => 2000,
        ])->assertCreated();

        $this->assertSame('projects/1/cover/b.jpg', $project->fresh()->cover_key);
        $this->assertSame(1, $project->files()->where('slot', 'cover')->count());
    }

    public function test_external_user_cannot_upload_to_unassigned_project(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        $external = $this->makeUser('external');
        $project = Project::create(['title' => 'Locked', 'stage' => 'idea']);

        $this->actingAs($external, 'sanctum')->postJson("/api/projects/{$project->id}/files", [
            'slot' => 'file', 'key' => 'projects/1/file/x.pdf', 'name' => 'x.pdf',
        ])->assertStatus(403);
    }

    public function test_sicala_executes_action_and_logs_usage(): void
    {
        config()->set('services.anthropic.key', 'sk-test');
        \Illuminate\Support\Facades\Http::fake([
            '*/v1/messages' => \Illuminate\Support\Facades\Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => json_encode([
                        'reply' => 'Moved it for you.',
                        'actions' => [['type' => 'move_stage', 'project' => 'Knot', 'stage' => 'produccion']],
                    ]),
                ]],
                'usage' => ['input_tokens' => 1200, 'output_tokens' => 80],
            ], 200),
        ]);

        $admin = $this->makeUser('admin');
        $project = \App\Models\Project::create(['title' => 'Knot', 'stage' => 'idea']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/ai/assistant', ['messages' => [['role' => 'user', 'content' => 'move Knot to production']]])
            ->assertOk()
            ->assertJsonPath('reply', 'Moved it for you.')
            ->assertJsonPath('changed', true);

        $this->assertSame('produccion', $project->fresh()->stage);
        $this->assertDatabaseHas('usage_events', [
            'feature' => 'assistant', 'input_tokens' => 1200, 'output_tokens' => 80,
        ]);
    }

    public function test_external_user_cannot_use_sicala(): void
    {
        $external = $this->makeUser('external');
        $this->actingAs($external, 'sanctum')
            ->postJson('/api/ai/assistant', ['messages' => [['role' => 'user', 'content' => 'hi']]])
            ->assertStatus(403);
    }

    public function test_admin_can_invite_and_change_role(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin, 'sanctum')->postJson('/api/settings/users', [
            'name' => 'New Person', 'email' => 'new.person@prodmee.test', 'role' => 'member',
        ])->assertCreated()->assertJsonPath('data.status', 'invited');

        $u = User::whereRaw('lower(email) = ?', ['new.person@prodmee.test'])->first();
        $this->assertNotNull($u);

        $this->actingAs($admin, 'sanctum')->putJson("/api/settings/users/{$u->id}", ['role' => 'admin'])
            ->assertOk()->assertJsonPath('data.role', 'admin');
    }

    public function test_api_key_is_write_only_and_reports_last4(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin, 'sanctum')->putJson('/api/settings/api-key', ['key' => 'sk-ant-secret-1234'])
            ->assertOk()->assertJsonPath('last4', '1234');

        $resp = $this->actingAs($admin, 'sanctum')->getJson('/api/settings/api-key')->assertOk();
        $resp->assertJsonPath('set', true)->assertJsonPath('last4', '1234')->assertJsonPath('source', 'settings');
        // The full key is never returned.
        $this->assertStringNotContainsString('sk-ant-secret-1234', $resp->getContent());
    }

    public function test_share_link_lifecycle_and_sanitization(): void
    {
        $admin = $this->makeUser('admin');
        $project = Project::create([
            'title' => 'Shared One', 'stage' => 'idea', 'logline' => 'A public hook',
            'tier' => '$10–20M', 'notes' => 'SECRET internal note', 'concept' => 'public concept',
        ]);

        // Enable share.
        $resp = $this->actingAs($admin, 'sanctum')->postJson("/api/projects/{$project->id}/share")->assertOk();
        $token = $resp->json('shareToken');
        $this->assertNotEmpty($token);

        // Public, unauthenticated fetch returns creative fields.
        $pub = $this->getJson("/api/share/{$token}")->assertOk();
        $pub->assertJsonPath('data.title', 'Shared One')
            ->assertJsonPath('data.concept', 'public concept');
        // ...but never budget/tier or internal notes.
        $body = $pub->getContent();
        $this->assertStringNotContainsString('SECRET internal note', $body);
        $this->assertStringNotContainsString('10–20M', $body);

        // Revoke disables it.
        $this->actingAs($admin, 'sanctum')->deleteJson("/api/projects/{$project->id}/share")->assertOk();
        $this->getJson("/api/share/{$token}")->assertStatus(404);
    }

    public function test_usage_aggregation_returns_series(): void
    {
        $admin = $this->makeUser('admin');
        \App\Models\UsageEvent::create([
            'user_id' => $admin->id, 'feature' => 'assistant', 'model' => 'm',
            'input_tokens' => 100, 'output_tokens' => 20, 'cost_estimate' => 0.001, 'created_at' => now(),
        ]);
        \App\Models\UsageEvent::create([
            'user_id' => $admin->id, 'feature' => 'autofill', 'model' => 'm',
            'input_tokens' => 50, 'output_tokens' => 10, 'cost_estimate' => 0.0005, 'created_at' => now(),
        ]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/settings/usage?range=daily')
            ->assertOk()
            ->assertJsonPath('totals.calls', 2)
            ->assertJsonPath('totals.inputTokens', 150)
            ->assertJsonCount(2, 'byFeature');
    }
}
