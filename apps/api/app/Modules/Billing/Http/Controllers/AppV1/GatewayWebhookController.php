<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\AppV1;

use App\Modules\Billing\Actions\ReconcilePayment;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Services\Gateways\PaymentGatewayManager;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use stdClass;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `POST /billing/gateway-webhooks/{gateway}` (guest; the driver verifies the signature),
 * API.md §1.7. A verified call re-reads the payment from the gateway (never trusting the
 * body) and answers 200 `{}` whether or not anything changed; a bad signature is 400
 * `invalid_webhook`; an unknown gateway is 404.
 */
final class GatewayWebhookController extends BillingController
{
    public function __invoke(Request $request, string $gateway, PaymentGatewayManager $gateways, ReconcilePayment $reconcile): JsonResponse
    {
        if (! $gateways->has($gateway)) {
            throw new NotFoundHttpException;
        }

        $webhook = $gateways->driver($gateway)->parseWebhook($request);

        if ($webhook === null) {
            throw new ApiException('invalid_webhook', 'billing.errors.invalid_webhook', 400);
        }

        $payment = Payment::query()
            ->where('gateway', $gateway)
            ->where('gateway_reference', $webhook->reference)
            ->first();

        if ($payment !== null) {
            $reconcile->handle($payment, Actor::system());
        }

        return $this->ok(new stdClass);
    }
}
