<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.7 `coupons` (coupons and organization-scoped vouchers, §13.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 40)->unique(); // UPPERCASE; vouchers V- + 10 [A-Z0-9]
            $table->string('kind', 40); // enum:CouponKind
            $table->string('discount_type', 40); // enum:DiscountType
            $table->integer('percent_bps')->nullable(); // 1–10000
            $table->bigInteger('amount_minor')->nullable(); // fixed discount; voucher original value
            $table->bigInteger('balance_minor')->nullable(); // voucher remaining value
            $table->string('applies_to', 40)->default('any'); // enum:CouponScope
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->integer('max_redemptions')->nullable(); // null = unlimited
            $table->integer('redemptions_count')->default(0);
            $table->integer('per_organization_limit')->nullable()->default(1); // vouchers: null
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('reason')->nullable();
            $table->foreignId('source_competition_id')->nullable()->constrained('competitions')->nullOnDelete();
            $table->unsignedBigInteger('created_by_admin_id')->nullable()->index(); // ref → admins (unconstrained: later module)
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
