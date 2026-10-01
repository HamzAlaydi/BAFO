<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.7 `competition_sponsorships` (R4, §13.5). A row exists only when mode ≠ none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_sponsorships', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('competition_id')->unique()->constrained('competitions')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete(); // the sponsor (the issuer)
            $table->string('mode', 40); // enum:SponsorshipMode
            $table->integer('max_passes')->nullable(); // null = no cap
            $table->bigInteger('unit_price_minor'); // frozen at first funding
            $table->integer('vat_rate_bp')->default(1500);
            $table->integer('funded_passes')->default(0);
            $table->string('status', 40)->default('draft'); // enum:SponsorshipStatus
            $table->timestampTz('settled_at')->nullable();
            $table->integer('unused_count')->nullable();
            $table->foreignId('voucher_coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->foreignId('configured_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_sponsorships');
    }
};
