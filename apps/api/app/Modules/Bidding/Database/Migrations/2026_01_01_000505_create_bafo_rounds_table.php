<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.6 `bafo_rounds` (at most one per competition in the MVP). tstz6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bafo_rounds', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('competition_id')->unique()->constrained('competitions')->cascadeOnDelete();
            $table->foreignId('started_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 40); // enum:BafoRoundStatus
            $table->timestampTz('starts_at', 6);
            $table->timestampTz('cutoff_at', 6);
            $table->timestampTz('ended_at', 6)->nullable();
            $table->integer('shortlist_count');
            $table->timestampsTz(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bafo_rounds');
    }
};
