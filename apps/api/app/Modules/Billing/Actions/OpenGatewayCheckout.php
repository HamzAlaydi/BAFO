<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Data\GatewayPaymentState;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Services\Gateways\FakePaymentGateway;
use App\Modules\Billing\Services\Gateways\PaymentGatewayManager;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;

/**
 * The after-commit half of checkout creation (ARCHITECTURE §13.1 steps 2–4):
 *
 * - a zero total (e.g. covered by a voucher) is fulfilled at once without the gateway: status
 *   `succeeded`, gateway `fake`, reference `internal:{public_id}`, no redirect;
 * - otherwise `PaymentGateway::createCheckout()` gives the reference and the hosted page URL. A
 *   gateway error fails the payment (its pending rows are released) and is rethrown.
 */
final readonly class OpenGatewayCheckout
{
    public function __construct(
        private PaymentGatewayManager $gateways,
        private HandleGatewayResult $handleResult,
    ) {}

    public function handle(Payment $payment, Actor $actor): Payment
    {
        if ($payment->total_minor === 0) {
            $payment->forceFill([
                'gateway' => FakePaymentGateway::NAME,
                'gateway_reference' => 'internal:'.$payment->public_id,
            ])->save();

            return $this->handleResult->handle($payment, GatewayPaymentState::paid(0, $payment->currency), $actor);
        }

        try {
            $session = $this->gateways->for($payment)->createCheckout($payment, $payment->return_url);
        } catch (ApiException $e) {
            $this->handleResult->handle($payment, GatewayPaymentState::failed($e->errorCode), $actor);

            throw $e;
        }

        $payment->forceFill([
            'gateway_reference' => $session->reference,
            'redirect_url' => $session->redirectUrl,
        ])->save();

        return $payment;
    }
}
