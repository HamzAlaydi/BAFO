<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ARCHITECTURE §5.7 `subscriptions` (§6.5, §13.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('source', 40); // enum:SubscriptionSource
            $table->string('interval', 40)->nullable(); // enum:BillingInterval (paid only)
            $table->smallInteger('seats');
            $table->string('status', 40); // enum:SubscriptionStatus
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            // Snapshot from the payment (null for trial or grant).
            $table->bigInteger('unit_price_minor')->nullable();
            $table->bigInteger('subtotal_minor')->nullable();
            $table->bigInteger('discount_minor')->nullable();
            $table->bigInteger('credit_minor')->nullable();
            $table->bigInteger('vat_minor')->nullable();
            $table->bigInteger('total_minor')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('replaces_subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->unsignedBigInteger('granted_by_admin_id')->nullable()->index(); // ref → admins (unconstrained: later module)
            $table->text('grant_reason')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->jsonb('reminders_sent')->default('[]'); // e.g. [7, 3, 1]
            $table->timestampsTz();

            $table->index(['organization_id', 'status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
