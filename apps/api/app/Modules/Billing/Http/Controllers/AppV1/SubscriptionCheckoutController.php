<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\AppV1;

use App\Modules\Billing\Actions\CreateSubscriptionCheckout;
use App\Modules\Billing\Http\Requests\StoreSubscriptionCheckoutRequest;
use App\Modules\Billing\Http\Resources\PaymentResource;
use App\Support\Auth\CurrentActor;
use Illuminate\Http\JsonResponse;

/**
 * `POST /billing/checkout/subscription` (perm `billing.purchase` via the route, optional
 * `Idempotency-Key`), API.md §1.7. 201 with the Payment; the client navigates to
 * `redirect_url` (null, and the status `succeeded`, when the total is 0).
 */
final class SubscriptionCheckoutController extends BillingController
{
    public function __invoke(StoreSubscriptionCheckoutRequest $request, CreateSubscriptionCheckout $checkout): JsonResponse
    {
        $user = $this->user($request);
        $payment = $checkout->handle($user, $this->organization($user), $request->checkoutInput(), CurrentActor::get());

        return $this->created(PaymentResource::make($payment));
    }
}
