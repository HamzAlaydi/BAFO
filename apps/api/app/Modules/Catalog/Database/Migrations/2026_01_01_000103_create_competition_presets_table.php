<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.2 `competition_presets`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_presets', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 60)->unique();
            $table->jsonb('name');
            $table->jsonb('description');
            $table->string('direction', 40); // enum:Direction
            $table->string('format', 40); // enum:Format
            $table->jsonb('rules'); // the RulesInput keys of API.md §2.6, without prices
            // CONTRACT-GAP: §5.2 gives no default for presets.sort_order; 0 like the other lookups.
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_presets');
    }
};
