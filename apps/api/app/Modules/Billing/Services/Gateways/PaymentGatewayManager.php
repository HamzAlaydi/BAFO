<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services\Gateways;

use App\Modules\Billing\Contracts\PaymentGateway;
use App\Modules\Billing\Models\Payment;
use App\Support\Exceptions\ApiException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

/**
 * Resolves PaymentGateway drivers by name (ARCHITECTURE §13.6, §15.1). New checkouts use the
 * configured driver (`bafo.billing.gateway.driver`); an existing payment is always handled by
 * the driver recorded in `payments.gateway`. The fake driver is refused in production (503
 * `gateway_not_configured`).
 */
final readonly class PaymentGatewayManager
{
    /**
     * @var array<string, class-string<PaymentGateway>>
     */
    public const array DRIVERS = [
        FakePaymentGateway::NAME => FakePaymentGateway::class,
        MoyasarPaymentGateway::NAME => MoyasarPaymentGateway::class,
    ];

    public function __construct(private Container $container) {}

    public function defaultName(): string
    {
        $driver = config('bafo.billing.gateway.driver', FakePaymentGateway::NAME);

        return is_string($driver) ? $driver : FakePaymentGateway::NAME;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, self::DRIVERS);
    }

    public function driver(?string $name = null): PaymentGateway
    {
        $name ??= $this->defaultName();

        if (! $this->has($name)) {
            throw new InvalidArgumentException("Unknown payment gateway [{$name}].");
        }

        // The fake gateway is a development tool: production refuses it rather than open
        // checkouts nobody can pay (SECURITY_REVIEW S-10).
        if ($name === FakePaymentGateway::NAME && $this->container->make(Application::class)->isProduction()) {
            throw new ApiException('gateway_not_configured', 'billing.errors.gateway_not_configured', 503);
        }

        /** @var PaymentGateway $driver */
        $driver = $this->container->make(self::DRIVERS[$name]);

        return $driver;
    }

    public function for(Payment $payment): PaymentGateway
    {
        return $this->driver($payment->gateway);
    }
}
