<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.6 `offer_rejections` (rejected attempts, kept for disputes). tstz6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_rejections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained('participants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->bigInteger('amount_minor')->nullable();
            $table->string('code', 60); // error code (CONVENTIONS §8.5)
            $table->string('idempotency_key', 64)->nullable();
            $table->string('stage', 10)->nullable();
            $table->timestampTz('received_at', 6); // app time
            $table->timestampTz('db_time', 6)->nullable(); // DB clock when the lock was taken
            $table->string('channel', 10)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestampTz('created_at', 6);

            $table->index(['competition_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_rejections');
    }
};
