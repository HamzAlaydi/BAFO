<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.3 `organization_category` (pivot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_category', function (Blueprint $table): void {
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->primary(['organization_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_category');
    }
};
