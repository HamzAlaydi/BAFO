<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id');
            $table->string('key', 64);
            $table->string('method', 8);
            $table->string('path', 255);
            $table->char('request_hash', 64);
            $table->smallInteger('response_status')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('created_at');

            $table->unique(['scope_type', 'scope_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
