<?php

declare(strict_types=1);

namespace App\Modules\Billing\Console\Commands;

use App\Modules\Billing\Actions\ReconcilePayment;
use App\Modules\Billing\Actions\VoidExpiredPassHolds;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Models\Payment;
use App\Support\Auth\Actor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * `billing:reconcile-payments` (every five minutes, ARCHITECTURE §12):
 *
 * - pending payments older than 5 minutes → `PaymentGateway::fetch()` → HandleGatewayResult;
 *   still unpaid past `expires_at` → expired;
 * - pending passes past `hold_expires_at` → void.
 *
 * One payment's gateway error never stops the others.
 */
final class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'billing:reconcile-payments';

    protected $description = 'Reconcile pending payments with the gateway and release expired holds';

    public function handle(ReconcilePayment $reconcile, VoidExpiredPassHolds $voidHolds): int
    {
        $actor = Actor::system();
        $checked = 0;

        Payment::query()
            ->where('status', PaymentStatus::Pending->value)
            ->where('created_at', '<=', now()->subMinutes(5))
            ->orderBy('id')
            ->each(function (Payment $payment) use ($reconcile, $actor, &$checked): void {
                try {
                    $reconcile->handle($payment, $actor);
                    $checked++;
                } catch (Throwable $e) {
                    Log::warning('Payment reconciliation failed.', ['payment_id' => $payment->public_id, 'error' => $e->getMessage()]);
                }
            });

        $voided = $voidHolds->handle($actor);

        $this->components->info("Reconciled {$checked} payments; voided {$voided} expired pass holds.");

        return self::SUCCESS;
    }
}
