<?php

declare(strict_types=1);

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Data\CheckoutSession;
use App\Modules\Billing\Data\GatewayPaymentState;
use App\Modules\Billing\Data\GatewayWebhook;
use App\Modules\Billing\Models\Payment;
use App\Support\Exceptions\ApiException;
use Illuminate\Http\Request;

/**
 * A payment gateway driver (ARCHITECTURE §13.6): `fake` (default) or `moyasar`.
 */
interface PaymentGateway
{
    /**
     * Opens the hosted checkout for the payment.
     *
     * @throws ApiException gateway_error (502) or gateway_not_configured (503)
     */
    public function createCheckout(Payment $payment, string $returnUrl): CheckoutSession;

    /**
     * The payment's state at the gateway now.
     *
     * @throws ApiException gateway_error (502) or gateway_not_configured (503)
     */
    public function fetch(Payment $payment): GatewayPaymentState;

    /**
     * A verified webhook, or null when the signature is invalid (→ 400 invalid_webhook).
     */
    public function parseWebhook(Request $request): ?GatewayWebhook;
}
