<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.3 `account_deletion_requests` (one pending request per user).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletion_requests', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('scope', 40); // enum:DeletionScope
            $table->text('reason')->nullable();
            $table->string('status', 40); // enum:DeletionStatus
            $table->timestampTz('scheduled_for'); // requested + 14 days
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement("CREATE UNIQUE INDEX account_deletion_requests_one_pending_per_user ON account_deletion_requests (user_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
