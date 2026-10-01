<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.7 `plans`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 40)->unique(); // single, plus, pro, custom
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->jsonb('features')->default('[]'); // list of {ar, en}
            $table->smallInteger('seats')->nullable(); // total users including the owner; null for custom
            $table->bigInteger('monthly_price_minor')->nullable(); // excl. VAT; null for custom
            $table->bigInteger('annual_price_minor')->nullable();
            $table->bigInteger('monthly_list_price_minor')->nullable(); // struck-through "before" price
            $table->bigInteger('annual_list_price_minor')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
