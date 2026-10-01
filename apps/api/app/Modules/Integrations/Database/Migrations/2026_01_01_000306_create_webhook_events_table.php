<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.4 `webhook_events` (transactional outbox). The public id is the Standard
 * Webhooks `webhook-id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('type', 60);
            $table->string('subject_type', 40); // morph alias
            $table->unsignedBigInteger('subject_id');
            $table->bigInteger('sequence'); // per (subject_type, subject_id), max + 1
            $table->jsonb('payload'); // the full envelope body (API.md §4.2)
            $table->timestampTz('occurred_at', 6);
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['organization_id', 'occurred_at']);
        });

        DB::statement('CREATE INDEX webhook_events_undispatched_index ON webhook_events (created_at) WHERE dispatched_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
