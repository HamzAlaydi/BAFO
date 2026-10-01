<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.4 `external_refs` (ERP keys attached to vendors, competitions, invitations,
 * awards and organizations through a morph alias).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_refs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('refable_type', 40); // morph alias
            $table->unsignedBigInteger('refable_id');
            $table->string('system', 60); // ^[a-z0-9_]+(:[a-z0-9_]+)?$
            $table->string('type', 60);
            $table->string('value', 120);
            $table->string('number', 120)->nullable();
            $table->string('url', 500)->nullable();
            $table->unsignedBigInteger('created_by_api_client_id')->nullable()->index(); // ref → api_clients (unconstrained per §5.4)
            $table->timestampsTz();

            $table->unique(['organization_id', 'refable_type', 'system', 'type', 'value']);
            $table->index(['refable_type', 'refable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_refs');
    }
};
