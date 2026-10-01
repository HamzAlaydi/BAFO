<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.7 `sponsored_passes` (§6.3). At most one live pass per invitation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsored_passes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('sponsorship_id')->constrained('competition_sponsorships')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained('competitions')->restrictOnDelete();
            $table->foreignId('invitation_id')->constrained('invitations')->restrictOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete(); // invitee org, once known
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete(); // null for freed_slot, admin_grant
            $table->string('source', 40); // enum:PassSource
            $table->string('status', 40); // enum:PassStatus
            $table->string('release_reason', 40)->nullable(); // enum:PassReleaseReason
            $table->timestampTz('hold_expires_at')->nullable(); // pending only
            $table->timestampTz('reserved_at')->nullable();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement("CREATE UNIQUE INDEX sponsored_passes_one_live_per_invitation ON sponsored_passes (invitation_id) WHERE status IN ('pending', 'reserved', 'joined')");
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsored_passes');
    }
};
