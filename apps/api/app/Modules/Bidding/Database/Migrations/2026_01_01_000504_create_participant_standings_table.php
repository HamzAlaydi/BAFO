<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.6 `participant_standings` (derived; rebuildable from the ledger minus voids).
 * tstz6; only `updated_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participant_standings', function (Blueprint $table): void {
            $table->foreignId('participant_id')->primary()->constrained('participants')->cascadeOnDelete();
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->foreignId('current_offer_id')->nullable()->constrained('offers')->restrictOnDelete(); // latest non-voided offer
            $table->bigInteger('current_amount_minor')->nullable();
            $table->bigInteger('current_rank_key')->nullable();
            $table->timestampTz('current_at', 6)->nullable();
            $table->bigInteger('current_seq')->nullable();
            $table->bigInteger('first_amount_minor')->nullable();
            $table->integer('offers_count')->default(0);
            $table->integer('rank')->nullable(); // 1 = leader
            $table->boolean('is_leader')->default(false);
            $table->boolean('bafo_shortlisted')->default(false);
            $table->bigInteger('bafo_reference_amount_minor')->nullable();
            $table->foreignId('bafo_offer_id')->nullable()->constrained('offers')->restrictOnDelete();
            $table->timestampTz('last_offer_at', 6)->nullable();
            $table->timestampTz('updated_at', 6);

            $table->index(['competition_id', 'current_rank_key', 'current_at', 'current_seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_standings');
    }
};
