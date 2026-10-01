<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.5 `competition_extensions` (tstz6; no updated_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_extensions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('competition_id')->constrained('competitions')->cascadeOnDelete();
            $table->string('kind', 40); // enum:ExtensionKind
            $table->timestampTz('previous_close_at', 6);
            $table->timestampTz('new_close_at', 6);
            $table->unsignedBigInteger('triggered_by_offer_id')->nullable()->index(); // ref → offers (unconstrained: later module)
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('actor_admin_id')->nullable()->index(); // ref → admins (unconstrained: later module)
            $table->text('reason')->nullable(); // required for manual and admin
            $table->timestampTz('created_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_extensions');
    }
};
