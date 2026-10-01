<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.3 `organizations` (soft deletes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name', 150);
            $table->string('legal_name_ar', 200)->nullable();
            $table->string('legal_name_en', 200)->nullable();
            $table->string('cr_number', 10)->unique();
            $table->boolean('vat_registered')->default(false);
            $table->string('vat_number', 15)->nullable()->index();
            $table->foreignId('region_id')->index()->constrained('regions')->restrictOnDelete();
            $table->string('city', 100);
            $table->string('address_building_number', 4)->nullable();
            $table->string('address_street', 150)->nullable();
            $table->string('address_district', 150)->nullable();
            $table->string('address_postal_code', 5)->nullable();
            $table->string('address_additional_number', 4)->nullable();
            $table->string('address_short', 8)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('email', 255);
            $table->string('phone', 20);
            $table->foreignId('logo_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->foreignId('profile_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->boolean('visible_in_suggestions')->default(true);
            $table->string('status', 40)->default('active')->index(); // enum:OrganizationStatus
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->boolean('api_enabled')->default(false);
            $table->boolean('auction_enabled')->default(false);
            $table->boolean('sponsorship_enabled')->default(false);
            $table->timestampTz('trial_used_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
