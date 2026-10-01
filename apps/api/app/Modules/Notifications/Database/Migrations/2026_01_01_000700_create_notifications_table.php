<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.8 `notifications` (Laravel database-notification shape). The id is a lowercase
 * ULID string set by the base notification class: the one exception to `id` / `public_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->string('type', 255); // notification class
            $table->morphs('notifiable'); // bigint id; alias `user`
            $table->jsonb('data'); // {type, params, subject: {type, id}, route} (§11.2)
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
