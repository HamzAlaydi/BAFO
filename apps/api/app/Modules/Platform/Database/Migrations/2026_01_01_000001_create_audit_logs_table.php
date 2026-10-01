<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->timestampTz('occurred_at', 6);
            // ref → organizations (unconstrained: later module)
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('actor_type', 40);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_label', 255)->nullable();
            $table->string('action', 80)->index();
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_public_id', 26)->nullable();
            $table->jsonb('changes')->nullable();
            $table->jsonb('meta')->nullable();
            $table->string('channel', 10)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_id', 64)->nullable();

            $table->index(['subject_type', 'subject_id']);
        });

        DB::statement('CREATE INDEX audit_logs_organization_id_occurred_at_index ON audit_logs (organization_id, occurred_at DESC)');

        // Append-only tables (audit_logs here; the Bidding ledgers reuse the function).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION bafo_forbid_update_delete() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'table % is append-only', TG_TABLE_NAME; END $$;
            SQL);

        DB::statement('CREATE TRIGGER audit_logs_append_only BEFORE UPDATE OR DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION bafo_forbid_update_delete()');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');

        // Later migrations that reuse the function are rolled back first.
        DB::statement('DROP FUNCTION IF EXISTS bafo_forbid_update_delete()');
    }
};
