<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.7 `payments` (the checkout order and payment record, §6.4, §13.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('purpose', 40); // enum:PaymentPurpose
            $table->string('status', 40)->default('pending'); // enum:PaymentStatus
            $table->string('gateway', 20); // fake, moyasar
            $table->string('gateway_reference', 120)->nullable()->unique();
            $table->char('currency', 3)->default('SAR');
            $table->bigInteger('subtotal_minor'); // sum of the lines
            $table->bigInteger('discount_minor')->default(0); // coupon or voucher
            $table->bigInteger('credit_minor')->default(0); // pro-rata upgrade credit
            $table->integer('vat_rate_bp')->default(1500);
            $table->bigInteger('vat_minor'); // Money::vat(subtotal − discount − credit)
            $table->bigInteger('total_minor'); // subtotal − discount − credit + vat (≥ 0)
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('idempotency_key', 64)->nullable();
            $table->string('return_url', 1000);
            $table->string('redirect_url', 1000)->nullable();
            $table->jsonb('metadata');
            $table->timestampTz('expires_at');
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('refunded_at')->nullable();
            $table->string('failure_code', 60)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->string('refund_reference', 120)->nullable();
            $table->unsignedBigInteger('refunded_by_admin_id')->nullable()->index(); // ref → admins (unconstrained: later module)
            $table->string('manual_reference', 120)->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'idempotency_key']);
            $table->index(['status', 'created_at']);
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
