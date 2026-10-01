<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.5 `participants` (created on join; the participation lock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participants', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('invitation_id')->unique()->constrained('invitations')->restrictOnDelete();
            $table->smallInteger('alias_no'); // "Participant {n}"
            $table->string('entitlement_source', 40); // enum:EntitlementSource
            $table->string('terms_version', 40);
            $table->timestampTz('terms_accepted_at');
            $table->string('terms_ip', 45)->nullable();
            $table->foreignId('joined_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['competition_id', 'organization_id']);
            $table->unique(['competition_id', 'alias_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participants');
    }
};
