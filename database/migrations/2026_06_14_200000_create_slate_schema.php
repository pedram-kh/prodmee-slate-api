<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Invitation audit trail (who invited whom, with what role).
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('role')->default('member');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        // One-time login codes for passwordless OTP auth.
        Schema::create('login_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('logline')->nullable();
            $table->text('tagline')->nullable();
            $table->string('format')->default('Series');
            $table->string('genre')->nullable();
            $table->string('stage')->default('idea');
            $table->string('origin')->default('interno'); // interno | externo
            $table->string('tier')->nullable();
            $table->string('language')->nullable();
            $table->string('episodes')->nullable();
            $table->string('territory')->nullable();
            $table->text('concept')->nullable();
            $table->text('why_now')->nullable();
            $table->text('references_text')->nullable();
            $table->text('participants')->nullable();
            $table->text('packaging')->nullable();
            $table->text('notes')->nullable();
            $table->string('cover_key')->nullable(); // S3 key for cover image
            $table->string('share_token')->nullable()->unique();
            $table->timestamps();
        });

        // Per-project access (Axis 2). relation: member | external.
        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('relation')->default('member');
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('buyers', function (Blueprint $table) {
            $table->id();
            $table->string('platform');
            $table->string('contact')->nullable();
            $table->string('role')->nullable();
            $table->string('territory')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('pitches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('preparando');
            $table->date('last_contact')->nullable();
            $table->text('next')->nullable();
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name');
            $table->text('body');
            $table->timestamps();
        });

        // Unified file table; slot distinguishes cover/script/bible/budget/file.
        Schema::create('project_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('slot')->default('file'); // file | cover | script | bible | budget
            $table->string('name');
            $table->string('label')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('s3_key');
            $table->timestamps();
        });

        Schema::create('project_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('url');
            $table->timestamps();
        });

        // Sparse checklist state: a row exists only for toggled items.
        Schema::create('project_checklist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('item_id');
            $table->boolean('done')->default(false);
            $table->timestamps();
            $table->unique(['project_id', 'item_id']);
        });

        // Key/value app settings (e.g. encrypted Anthropic API key).
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // One row per AI call, sourced from Anthropic response usage.
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature'); // assistant | autofill
            $table->string('model');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_estimate', 12, 6)->default(0);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('project_checklist');
        Schema::dropIfExists('project_links');
        Schema::dropIfExists('project_files');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('pitches');
        Schema::dropIfExists('buyers');
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('login_codes');
        Schema::dropIfExists('invitations');
    }
};
