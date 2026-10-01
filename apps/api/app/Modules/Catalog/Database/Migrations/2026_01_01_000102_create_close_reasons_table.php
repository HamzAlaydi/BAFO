<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.2 `close_reasons`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('close_reasons', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 60)->unique();
            $table->string('kind', 40); // enum:CloseReasonKind
            $table->jsonb('name');
            $table->boolean('requires_note')->default(false);
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('close_reasons');
    }
};
