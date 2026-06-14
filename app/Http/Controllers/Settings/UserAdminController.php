<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class UserAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'member' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => $this->payload($u));

        return response()->json(['data' => $users]);
    }

    /**
     * Invite a user: creates an account in 'invited' status and emails them a
     * link to the login screen. No code is sent — the user requests an OTP
     * themselves from that screen.
     */
    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'member', 'external'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'role' => $data['role'],
            'status' => 'invited',
        ]);

        Invitation::create([
            'email' => $user->email,
            'role' => $user->role,
            'invited_by' => $request->user()->id,
            'user_id' => $user->id,
        ]);

        $this->sendInvitation($user);

        return response()->json(['data' => $this->payload($user)], 201);
    }

    /**
     * Email the invitee a pointer to the login screen. Mail failures must not
     * fail the invite itself (the account is already created).
     */
    private function sendInvitation(User $user): void
    {
        $roleLabel = match ($user->role) {
            'admin' => 'an Admin',
            'external' => 'an External collaborator',
            default => 'a Team member',
        };
        $loginUrl = rtrim(config('app.frontend_url', ''), '/') . '/login';

        try {
            Mail::to($user->email)->send(new InvitationMail($user->name, $roleLabel, $loginUrl));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', Rule::in(['admin', 'member', 'external'])],
            'status' => ['sometimes', Rule::in(['invited', 'active', 'disabled'])],
        ]);

        // Don't allow an admin to lock themselves out of admin.
        if (($data['role'] ?? $user->role) !== 'admin' && $user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot remove your own admin role.'], 422);
        }

        $user->fill($data)->save();

        return response()->json(['data' => $this->payload($user)]);
    }

    /**
     * Soft-disable a user and revoke their tokens, preserving history.
     */
    public function deactivate(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }
        $user->update(['status' => 'disabled']);
        $user->tokens()->delete();

        return response()->json(['message' => 'User deactivated.']);
    }

    /**
     * Permanently delete a user. Schema FKs handle the rest: project access is
     * detached (cascade), comments and usage events keep their rows with a null
     * user_id. Tokens have no FK cascade, so revoke them explicitly first.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }

    private function payload(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'status' => $u->status,
            'lastLogin' => $u->last_login_at?->toIso8601String(),
        ];
    }
}
