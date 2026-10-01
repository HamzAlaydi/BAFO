<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.9 `admins` (Filament guard `admin`, §8.7, §16).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 150);
            $table->string('email', 255)->unique(); // lowercased
            $table->string('password', 255);
            $table->string('role', 40)->default('operator'); // enum:AdminRole
            $table->boolean('is_active')->default(true);
            $table->text('app_authentication_secret')->nullable(); // Filament MFA (encrypted cast)
            $table->text('app_authentication_recovery_codes')->nullable(); // encrypted cast
            $table->timestampTz('last_login_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
