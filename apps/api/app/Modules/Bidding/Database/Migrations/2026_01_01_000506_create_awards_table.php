<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.6 `awards` (tstz6; at most one issued award per competition).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awards', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('competition_id')->constrained('competitions')->restrictOnDelete();
            $table->foreignId('participant_id')->constrained('participants')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete(); // the winner
            $table->foreignId('offer_id')->constrained('offers')->restrictOnDelete(); // the winner's current offer at award time
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 40); // enum:AwardStatus
            $table->boolean('is_leading_offer')->default(false);
            $table->integer('rank_at_award');
            $table->boolean('reserve_met')->nullable(); // null without a reserve
            $table->foreignId('justification_reason_id')->nullable()->constrained('close_reasons')->restrictOnDelete(); // kind award_justification
            $table->text('justification_text')->nullable();
            $table->text('message_to_winner')->nullable(); // ≤ 2000
            $table->text('internal_notes')->nullable(); // issuer-only
            $table->foreignId('awarded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('awarded_at', 6);
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('revoked_at', 6)->nullable();
            $table->text('revoke_reason')->nullable();
            $table->string('erp_sync_status', 40); // enum:ErpSyncStatus
            $table->string('erp_sync_message', 500)->nullable();
            $table->timestampTz('erp_synced_at', 6)->nullable();
            $table->char('ledger_head_hash', 64); // copied from the live state at award
            $table->timestampsTz(6);
        });

        DB::statement("CREATE UNIQUE INDEX awards_one_issued_per_competition ON awards (competition_id) WHERE status = 'issued'");
    }

    public function down(): void
    {
        Schema::dropIfExists('awards');
    }
};
