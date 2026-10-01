<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.7 `payment_lines`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('kind', 40); // enum:PaymentLineKind
            $table->jsonb('description'); // {ar, en}
            $table->integer('quantity');
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('net_minor'); // quantity × unit
            $table->string('ref_type', 40)->nullable(); // morph alias
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_lines');
    }
};
