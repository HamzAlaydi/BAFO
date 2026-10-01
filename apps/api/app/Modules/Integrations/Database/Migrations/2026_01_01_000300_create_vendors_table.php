<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.4 `vendors` (the issuer's own counterparty directory; the R1 "partner").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('name_en', 200)->nullable();
            $table->string('cr_number', 10)->nullable();
            $table->string('vat_number', 15)->nullable();
            $table->string('email', 255); // lowercased; where invitations go
            $table->string('contact_name', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->string('city', 100)->nullable();
            $table->string('status', 40)->default('active'); // enum:VendorStatus
            $table->foreignId('linked_organization_id')->nullable()->index()->constrained('organizations')->nullOnDelete();
            $table->string('source', 40); // enum:VendorSource
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'email']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
