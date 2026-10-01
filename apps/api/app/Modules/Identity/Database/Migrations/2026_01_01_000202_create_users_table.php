<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.3 `users` (soft deletes). Replaces the framework users migration (§2.2 item 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 150);
            $table->string('email', 255)->unique(); // lowercased
            $table->string('phone', 20)->nullable();
            $table->string('password', 255)->nullable(); // null while a team invitation is pending
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('locale', 2)->default('ar');
            $table->foreignId('avatar_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->string('status', 40)->default('active'); // enum:UserStatus
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
