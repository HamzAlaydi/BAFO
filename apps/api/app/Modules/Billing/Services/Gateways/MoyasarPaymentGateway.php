<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services\Gateways;

use App\Modules\Billing\Contracts\PaymentGateway;
use App\Modules\Billing\Data\CheckoutSession;
use App\Modules\Billing\Data\GatewayPaymentState;
use App\Modules\Billing\Data\GatewayWebhook;
use App\Modules\Billing\Models\Payment;
use App\Support\Exceptions\ApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The `moyasar` gateway (ARCHITECTURE §13.6): hosted invoices through the Moyasar REST API.
 * Configured by `bafo.billing.gateway.moyasar.*` (env MOYASAR_*); the repo carries no keys, so
 * without them every call is 503 `gateway_not_configured`.
 *
 *   createCheckout  POST {base_url}/v1/invoices (Basic auth: secret key)
 *                   {amount, currency, description, callback_url, metadata: {payment_id}} → {id, url}
 *   fetch           GET  {base_url}/v1/invoices/{id}: paid → paid; failed|expired → failed; else pending
 *   parseWebhook    `secret_token` must equal the webhook secret; reference = data.invoice_id ?? data.id
 */
final class MoyasarPaymentGateway implements PaymentGateway
{
    public const string NAME = 'moyasar';

    public function createCheckout(Payment $payment, string $returnUrl): CheckoutSession
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http->post('/v1/invoices', [
            'amount' => $payment->total_minor,
            'currency' => $payment->currency,
            'description' => $this->description($payment),
            'callback_url' => $returnUrl,
            'metadata' => ['payment_id' => $payment->public_id],
        ]));

        $id = $response->json('id');
        $url = $response->json('url');

        if (! is_string($id) || $id === '' || ! is_string($url) || $url === '') {
            throw $this->gatewayError('invalid_response');
        }

        return new CheckoutSession($id, $url);
    }

    public function fetch(Payment $payment): GatewayPaymentState
    {
        $reference = $payment->gateway_reference;

        if ($reference === null || $reference === '') {
            return GatewayPaymentState::pending();
        }

        $response = $this->send(fn (PendingRequest $http): Response => $http->get('/v1/invoices/'.rawurlencode($reference)));
        $status = $response->json('status');
        $amount = $response->json('amount');
        $currency = $response->json('currency');

        return match ($status) {
            'paid' => GatewayPaymentState::paid(is_numeric($amount) ? (int) $amount : -1, is_string($currency) ? $currency : ''),
            'failed', 'expired' => GatewayPaymentState::failed((string) $status),
            default => GatewayPaymentState::pending(),
        };
    }

    public function parseWebhook(Request $request): ?GatewayWebhook
    {
        $secret = $this->config('webhook_secret');
        $token = $request->input('secret_token');

        if ($secret === '' || ! is_string($token) || ! hash_equals($secret, $token)) {
            return null;
        }

        $reference = $request->input('data.invoice_id') ?? $request->input('data.id');

        return is_string($reference) && $reference !== '' ? new GatewayWebhook($reference) : null;
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        $secret = $this->config('secret_key');
        $baseUrl = rtrim($this->config('base_url'), '/');

        if ($secret === '' || $baseUrl === '') {
            throw new ApiException('gateway_not_configured', 'billing.errors.gateway_not_configured', 503);
        }

        $timeout = config('bafo.billing.gateway.moyasar.timeout_seconds', 15);

        try {
            $response = $call(Http::baseUrl($baseUrl)
                ->withBasicAuth($secret, '')
                ->acceptJson()
                ->timeout(is_numeric($timeout) ? (int) $timeout : 15));
        } catch (ConnectionException $e) {
            Log::warning('Moyasar connection failed.', ['error' => $e->getMessage()]);

            throw $this->gatewayError('connection_failed', $e);
        }

        if (! $response->successful()) {
            Log::warning('Moyasar request failed.', ['status' => $response->status()]);

            throw $this->gatewayError('http_'.$response->status());
        }

        return $response;
    }

    private function description(Payment $payment): string
    {
        $label = __('billing.gateway.description', ['id' => $payment->public_id]);

        return is_string($label) ? $label : 'BAFO '.$payment->public_id;
    }

    private function config(string $key): string
    {
        $value = config('bafo.billing.gateway.moyasar.'.$key, '');

        return is_string($value) ? $value : '';
    }

    private function gatewayError(string $reason, ?Throwable $previous = null): ApiException
    {
        return new ApiException('gateway_error', 'billing.errors.gateway_error', 502, previous: $previous, details: ['reason' => $reason]);
    }
}
