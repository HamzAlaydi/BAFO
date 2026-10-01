<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.3 `memberships` (v1: exactly one per user; one owner per organization).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('role', 40); // enum:OrgRole
            $table->boolean('can_award')->default(false);
            $table->boolean('can_purchase')->default(false);
            $table->string('status', 40); // enum:MembershipStatus
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('invite_token_hash', 64)->nullable()->unique();
            $table->timestampTz('invite_expires_at')->nullable();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX memberships_one_owner_per_organization ON memberships (organization_id) WHERE role = 'owner'");
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
