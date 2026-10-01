<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.4 `vendor_category` (pivot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_category', function (Blueprint $table): void {
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->primary(['vendor_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_category');
    }
};
