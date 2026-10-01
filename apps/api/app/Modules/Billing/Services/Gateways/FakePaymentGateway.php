<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services\Gateways;

use App\Modules\Billing\Contracts\PaymentGateway;
use App\Modules\Billing\Data\CheckoutSession;
use App\Modules\Billing\Data\GatewayPaymentState;
use App\Modules\Billing\Data\GatewayWebhook;
use App\Modules\Billing\Models\Payment;
use App\Support\Exceptions\ApiException;
use Illuminate\Http\Request;

/**
 * The `fake` gateway (ARCHITECTURE §13.6, D18): a local hosted page at `/pay/fake/{payment}`
 * with Approve and Decline buttons. The state lives in `payments.metadata.fake_state`
 * (`pending`, `paid` or `failed`). It never runs in production (the page's routes do not exist
 * there), and it has no webhooks.
 */
final class FakePaymentGateway implements PaymentGateway
{
    public const string NAME = 'fake';

    public const string STATE_KEY = 'fake_state';

    public function createCheckout(Payment $payment, string $returnUrl): CheckoutSession
    {
        if (app()->isProduction()) {
            throw new ApiException('gateway_not_configured', 'billing.errors.gateway_not_configured', 503);
        }

        $this->mark($payment, 'pending');

        return new CheckoutSession(
            'fake_'.$payment->public_id,
            rtrim((string) config('app.url'), '/').'/pay/fake/'.$payment->public_id,
        );
    }

    public function fetch(Payment $payment): GatewayPaymentState
    {
        return match ($payment->metadata[self::STATE_KEY] ?? 'pending') {
            'paid' => GatewayPaymentState::paid($payment->total_minor, $payment->currency),
            'failed' => GatewayPaymentState::failed('declined', $this->message('billing.fake_pay.declined_message')),
            default => GatewayPaymentState::pending(),
        };
    }

    public function parseWebhook(Request $request): ?GatewayWebhook
    {
        return null;
    }

    /**
     * Records the payer's choice on the hosted page.
     *
     * @param  'pending'|'paid'|'failed'  $state
     */
    public function mark(Payment $payment, string $state): void
    {
        $payment->metadata = [...$payment->metadata, self::STATE_KEY => $state];
        $payment->save();
    }

    private function message(string $key): string
    {
        $message = __($key);

        return is_string($message) ? $message : $key;
    }
}
