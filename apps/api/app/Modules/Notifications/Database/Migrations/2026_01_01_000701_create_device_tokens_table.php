<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.8 `device_tokens` (push registration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 512)->unique(); // FCM token
            $table->string('platform', 40); // enum:DevicePlatform
            $table->string('device_name', 120)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('locale', 2);
            $table->timestampTz('last_seen_at');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
