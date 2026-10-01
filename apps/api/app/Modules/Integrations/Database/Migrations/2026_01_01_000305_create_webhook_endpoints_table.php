<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.4 `webhook_endpoints` (soft deletes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('url', 1000);
            $table->string('description', 255)->nullable();
            $table->jsonb('event_types'); // catalogue types, or ["*"]
            $table->text('secret'); // Eloquent `encrypted` cast
            $table->string('status', 40)->default('active'); // enum:WebhookEndpointStatus
            $table->string('disabled_reason', 20)->nullable(); // manual | failing
            $table->timestampTz('failing_since')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_failure_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_api_client_id')->nullable()->constrained('api_clients')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
