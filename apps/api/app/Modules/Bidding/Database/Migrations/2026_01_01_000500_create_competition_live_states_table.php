<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.6 `competition_live_states` (1:1 with a published competition; created lazily
 * under the competition lock). tstz6; only `updated_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_live_states', function (Blueprint $table): void {
            $table->foreignId('competition_id')->primary()->constrained('competitions')->cascadeOnDelete();
            $table->bigInteger('version')->default(0);
            $table->bigInteger('last_seq')->default(0);
            $table->foreignId('leader_participant_id')->nullable()->constrained('participants')->nullOnDelete();
            $table->unsignedBigInteger('leader_offer_id')->nullable()->index(); // ref → offers (created in the next migration)
            $table->bigInteger('leader_amount_minor')->nullable();
            $table->integer('accepted_offer_count')->default(0);
            $table->integer('participants_with_offers')->default(0);
            $table->boolean('reserve_met')->nullable(); // null when there is no reserve
            $table->char('ledger_head_hash', 64)->nullable();
            $table->timestampTz('updated_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_live_states');
    }
};
