<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Web;

use App\Modules\Billing\Actions\HandleGatewayResult;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentLine;
use App\Modules\Billing\Services\Gateways\FakePaymentGateway;
use App\Support\Auth\Actor;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The fake gateway's hosted checkout page (ARCHITECTURE §13.6), `web` middleware, registered
 * only when the driver is `fake` outside production:
 *
 * - `GET /pay/fake/{payment}` shows BAFO as the merchant, the lines and the amounts, in Arabic
 *   or English (`?lang=`, else the payer's locale), with Approve and Decline;
 * - `POST …/approve` and `…/decline` (CSRF-protected) record the choice, run
 *   HandleGatewayResult synchronously and answer 303 to `return_url` + `payment={id}`. A
 *   payment that is no longer pending gets 409 with the page.
 */
final class FakeCheckoutController
{
    public function show(Request $request, Payment $payment): Response
    {
        $this->ensureFake($payment);
        $this->applyLocale($request, $payment);

        return $this->page($payment, 200);
    }

    public function approve(Request $request, Payment $payment, FakePaymentGateway $gateway, HandleGatewayResult $handle): Response|RedirectResponse
    {
        return $this->decide($request, $payment, 'paid', $gateway, $handle);
    }

    public function decline(Request $request, Payment $payment, FakePaymentGateway $gateway, HandleGatewayResult $handle): Response|RedirectResponse
    {
        return $this->decide($request, $payment, 'failed', $gateway, $handle);
    }

    /**
     * @param  'paid'|'failed'  $state
     */
    private function decide(Request $request, Payment $payment, string $state, FakePaymentGateway $gateway, HandleGatewayResult $handle): Response|RedirectResponse
    {
        $this->ensureFake($payment);
        $this->applyLocale($request, $payment);

        if (! $payment->isPending()) {
            return $this->page($payment, 409);
        }

        $gateway->mark($payment, $state);
        $payment = $handle->handle($payment, $gateway->fetch($payment), Actor::guest($request));

        return new RedirectResponse(self::returnUrl($payment), 303);
    }

    /**
     * `return_url` with `payment={public_id}` appended (CONVENTIONS §4.3 "Payment return").
     */
    public static function returnUrl(Payment $payment): string
    {
        $separator = str_contains($payment->return_url, '?') ? '&' : '?';

        return $payment->return_url.$separator.http_build_query(['payment' => $payment->public_id]);
    }

    private function page(Payment $payment, int $status): Response
    {
        $payment->loadMissing('lines', 'coupon');
        $locale = App::getLocale();

        return new Response(view('billing::fake-pay', [
            'payment' => $payment,
            'locale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
            'lines' => $payment->lines->map(static fn (PaymentLine $line): array => [
                'description' => $line->translated('description', $locale) ?? '',
                'quantity' => $line->quantity,
                'net' => Money::format($line->net_minor, $locale),
            ])->all(),
            'amounts' => [
                'subtotal' => Money::format($payment->subtotal_minor, $locale),
                'credit' => $payment->credit_minor > 0 ? Money::format($payment->credit_minor, $locale) : null,
                'discount' => $payment->discount_minor > 0 ? Money::format($payment->discount_minor, $locale) : null,
                'vat' => Money::format($payment->vat_minor, $locale),
                'total' => Money::format($payment->total_minor, $locale),
            ],
            'vatPercent' => rtrim(rtrim(number_format($payment->vat_rate_bp / 100, 2, '.', ''), '0'), '.'),
            'autoApprove' => $status === 200 && $payment->isPending() && (bool) config('bafo.billing.gateway.fake.auto_approve', false),
            'conflict' => $status === 409,
        ])->render(), $status, ['Cache-Control' => 'no-store']);
    }

    private function ensureFake(Payment $payment): void
    {
        if ($payment->gateway !== FakePaymentGateway::NAME || str_starts_with((string) $payment->gateway_reference, 'internal:')) {
            throw new NotFoundHttpException;
        }
    }

    private function applyLocale(Request $request, Payment $payment): void
    {
        $lang = $request->query('lang');
        $locale = is_string($lang) && in_array($lang, ['ar', 'en'], true)
            ? $lang
            : $payment->createdBy->locale;

        App::setLocale(in_array($locale, ['ar', 'en'], true) ? $locale : 'ar');
    }
}
