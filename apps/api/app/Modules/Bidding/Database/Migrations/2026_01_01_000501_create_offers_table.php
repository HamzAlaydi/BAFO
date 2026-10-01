<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.6 `offers`: the append-only, hash-chained offer ledger. No `updated_at`; a
 * BEFORE UPDATE OR DELETE trigger calls `bafo_forbid_update_delete()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The function is defined by the Platform `audit_logs` migration (§5.1). It is re-declared
        // here with the identical body (CREATE OR REPLACE is idempotent) so the ledger never depends
        // on migration timing; Platform owns it, so down() does not drop it.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION bafo_forbid_update_delete() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'table % is append-only', TG_TABLE_NAME; END $$;
            SQL);

        Schema::create('offers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('competition_id')->constrained('competitions')->restrictOnDelete();
            $table->foreignId('participant_id')->constrained('participants')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete(); // participant org (denormalised)
            $table->foreignId('submitted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->bigInteger('seq'); // per competition, from 1, gapless
            $table->string('stage', 40); // enum:OfferStage
            $table->bigInteger('amount_minor');
            $table->bigInteger('rank_key'); // amount for tender, −amount for auction
            $table->timestampTz('accepted_at', 6); // DB clock read after the competition lock
            $table->string('idempotency_key', 64);
            $table->string('channel', 40); // enum:Channel (web, ios, android, api)
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('outlier_confirmed')->default(false);
            $table->char('prev_hash', 64)->nullable(); // null for seq 1
            $table->char('hash', 64);
            $table->timestampTz('created_at', 6); // = accepted_at

            $table->unique(['competition_id', 'seq']);
            $table->unique(['participant_id', 'idempotency_key']);
            $table->index(['competition_id', 'participant_id', 'seq']);
        });

        DB::statement('ALTER TABLE offers ADD CONSTRAINT offers_amount_minor_positive CHECK (amount_minor > 0)');
        DB::statement('CREATE TRIGGER offers_append_only BEFORE UPDATE OR DELETE ON offers FOR EACH ROW EXECUTE FUNCTION bafo_forbid_update_delete()');
    }

    public function down(): void
    {
        Schema::dropIfExists('offers'); // drops the trigger with the table
    }
};
