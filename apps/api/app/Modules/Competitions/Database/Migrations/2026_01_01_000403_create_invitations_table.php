<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.5 `invitations` (§6.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->string('email', 255); // lowercased
            $table->string('name', 150)->nullable();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('status', 40)->default('draft'); // enum:InvitationStatus
            $table->boolean('sponsored_requested')->default(false);
            $table->char('token_hash', 64)->nullable()->unique();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invited_by_api_client_id')->nullable()->constrained('api_clients')->nullOnDelete();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('viewed_at')->nullable();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampTz('declined_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->string('decline_reason', 500)->nullable();
            $table->string('revoke_reason', 40)->nullable(); // enum:RevokeReason
            $table->timestampsTz();

            $table->unique(['competition_id', 'email']);
            $table->index(['organization_id', 'status']);
            $table->index(['competition_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
