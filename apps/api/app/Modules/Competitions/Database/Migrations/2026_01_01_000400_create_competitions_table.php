<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.5 `competitions` (soft deletes for drafts only; every time column is tstz6) and
 * the sequence `competition_reference_seq` used for `reference_no` at publish.
 */
return new class extends Migration
{
    public function up(): void
    {
        // `migrate:fresh` drops tables but not free-standing sequences: recreate it from 1.
        DB::statement('DROP SEQUENCE IF EXISTS competition_reference_seq');
        DB::statement('CREATE SEQUENCE competition_reference_seq START 1');

        Schema::create('competitions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('reference_no', 24)->nullable()->unique(); // BAFO-{T|A}-{YYYY}-{000001}
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete(); // issuer
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_api_client_id')->nullable()->constrained('api_clients')->nullOnDelete();
            $table->string('source', 40); // enum:CompetitionSource
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('category_other_text', 150)->nullable();
            $table->foreignId('region_id')->constrained('regions')->restrictOnDelete();
            $table->string('direction', 40); // enum:Direction
            $table->string('format', 40); // enum:Format
            $table->string('status', 40)->default('draft'); // enum:CompetitionStatus
            $table->char('currency', 3)->default('SAR');
            $table->string('preset_code', 60)->nullable();

            // Rules (typed columns, locked at publish: D6).
            $table->bigInteger('start_price_minor')->nullable();
            $table->bigInteger('reserve_price_minor')->nullable();
            $table->bigInteger('min_step_minor')->nullable();
            $table->integer('min_step_bps')->nullable();
            $table->integer('amount_granularity_minor')->default(100);
            $table->string('must_beat', 40)->nullable(); // enum:MustBeat
            $table->string('rank_visibility', 40)->default('leading_flag'); // enum:RankVisibility
            $table->boolean('show_prices')->default(false);
            $table->boolean('auto_extend_enabled')->default(false);
            $table->integer('auto_extend_window_seconds')->nullable();
            $table->integer('auto_extend_by_seconds')->nullable();
            $table->integer('auto_extend_max')->nullable();
            $table->integer('final_window_minutes')->nullable();
            $table->boolean('bafo_round_enabled')->default(false);
            $table->integer('bafo_duration_minutes')->nullable();
            $table->smallInteger('min_participants')->default(2);
            $table->string('result_publication', 40)->default('outcome_only'); // enum:ResultPublication

            // Schedule.
            $table->timestampTz('bidding_opens_at', 6)->nullable();
            $table->timestampTz('scheduled_close_at', 6)->nullable();
            $table->timestampTz('effective_close_at', 6)->nullable();
            $table->timestampTz('hard_stop_at', 6)->nullable();
            $table->timestampTz('final_window_starts_at', 6)->nullable();
            $table->timestampTz('invitation_cutoff_at', 6)->nullable();
            $table->integer('extension_count')->default(0);
            $table->jsonb('notified_thresholds')->default('[]');

            // Lifecycle stamps.
            $table->timestampTz('published_at', 6)->nullable();
            $table->timestampTz('opened_at', 6)->nullable();
            $table->timestampTz('final_window_started_at', 6)->nullable();
            $table->timestampTz('closed_at', 6)->nullable();
            $table->timestampTz('offers_opened_at', 6)->nullable();
            $table->timestampTz('awarded_at', 6)->nullable();
            $table->timestampTz('not_awarded_at', 6)->nullable();
            $table->timestampTz('cancelled_at', 6)->nullable();

            $table->foreignId('cancel_reason_id')->nullable()->constrained('close_reasons')->restrictOnDelete();
            $table->text('cancel_note')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('cancelled_by_admin_id')->nullable()->index(); // ref → admins (unconstrained: later module)
            $table->foreignId('not_awarded_reason_id')->nullable()->constrained('close_reasons')->restrictOnDelete();
            $table->text('not_awarded_note')->nullable();

            $table->timestampsTz(6);
            $table->softDeletesTz('deleted_at', 6);

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'bidding_opens_at']);
            $table->index(['status', 'effective_close_at']);
            $table->index(['status', 'final_window_starts_at']);
            $table->index(['status', 'invitation_cutoff_at']);
        });

        DB::statement('ALTER TABLE competitions ADD CONSTRAINT competitions_start_price_minor_positive CHECK (start_price_minor IS NULL OR start_price_minor > 0)');
        DB::statement('ALTER TABLE competitions ADD CONSTRAINT competitions_reserve_price_minor_positive CHECK (reserve_price_minor IS NULL OR reserve_price_minor > 0)');
        DB::statement('ALTER TABLE competitions ADD CONSTRAINT competitions_min_step_minor_positive CHECK (min_step_minor IS NULL OR min_step_minor > 0)');
        DB::statement('ALTER TABLE competitions ADD CONSTRAINT competitions_single_step_kind CHECK (NOT (min_step_minor IS NOT NULL AND min_step_bps IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('competitions');
        DB::statement('DROP SEQUENCE IF EXISTS competition_reference_seq');
    }
};
