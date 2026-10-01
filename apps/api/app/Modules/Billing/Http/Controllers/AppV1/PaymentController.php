<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\AppV1;

use App\Modules\Billing\Actions\ReconcilePayment;
use App\Modules\Billing\Http\Resources\PaymentResource;
use App\Modules\Billing\Models\Payment;
use App\Support\Auth\CurrentActor;
use Illuminate\Http\JsonResponse;

/**
 * `GET /billing/payments/{payment}` and `POST /billing/payments/{payment}/verify` (perm
 * `billing.view`, or the payer; another organization's payment is 404), API.md §1.7.
 * Clients poll `show` after the return from checkout, then call `verify` once.
 */
final class PaymentController extends BillingController
{
    public function show(Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        return $this->ok(PaymentResource::make($payment));
    }

    /**
     * Asks the gateway now (HandleGatewayResult). Idempotent: a settled payment is returned as is.
     */
    public function verify(Payment $payment, ReconcilePayment $reconcile): JsonResponse
    {
        $this->authorize('view', $payment);

        return $this->ok(PaymentResource::make($reconcile->handle($payment, CurrentActor::get())));
    }
}
