<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Billing module configuration: config('bafo.billing.*')
|--------------------------------------------------------------------------
|
| Environment keys: ARCHITECTURE §15.2. Runtime settings: ARCHITECTURE §15.3 (registered by
| BillingServiceProvider with Settings::defaults(); read them through BillingSettings).
| No real keys ever go in the repo: every external service runs its fake driver by default.
|
*/

return [

    // VAT is config, not a setting (ARCHITECTURE §15.3): 15% in basis points.
    'vat_rate_bp' => 1500,

    // PaymentGateway drivers (ARCHITECTURE §13.6): fake (default, local hosted page) or moyasar.
    'gateway' => [
        'driver' => env('PAYMENT_GATEWAY', 'fake'),

        'fake' => [
            // The hosted page auto-submits Approve after 1 s (E2E tests).
            'auto_approve' => (bool) env('PAYMENT_FAKE_AUTO_APPROVE', false),
        ],

        'moyasar' => [
            'base_url' => env('MOYASAR_BASE_URL', 'https://api.moyasar.com'),
            'secret_key' => env('MOYASAR_SECRET_KEY', ''),
            'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY', ''),
            'webhook_secret' => env('MOYASAR_WEBHOOK_SECRET', ''),
            'timeout_seconds' => 15,
        ],
    ],

    // EInvoicing driver (ARCHITECTURE §13.7): fake.
    'einvoicing' => [
        'driver' => env('EINVOICING_DRIVER', 'fake'),
    ],

    // Checkout return URLs must start with one of these (ARCHITECTURE §13.1 check 4).
    'allowed_return_urls' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BILLING_ALLOWED_RETURN_URLS', 'http://localhost:3000')),
    ))),

    // The seller printed on tax invoices and encoded in the ZATCA QR (placeholders only).
    'seller' => [
        'name_ar' => env('SELLER_NAME_AR', 'BAFO (placeholder)'),
        'name_en' => env('SELLER_NAME_EN', 'BAFO (placeholder)'),
        'vat_number' => env('SELLER_VAT_NUMBER', '300000000000003'),
        'cr_number' => env('SELLER_CR_NUMBER', '0000000000'),
        'address' => env('SELLER_ADDRESS', 'Riyadh, Saudi Arabia (placeholder)'),
    ],

    // Defaults of the Billing runtime settings (ARCHITECTURE §15.3). Admin-editable values in
    // app_settings, not environment keys.
    'settings' => [
        'billing.trial_days' => 30,
        'billing.trial_plan_code' => 'plus',
        'billing.custom_min_seats' => 4,
        'billing.custom_max_seats' => 50,
        'billing.custom_seat_monthly_price_minor' => 50_000,
        'billing.custom_seat_annual_price_minor' => 500_000,
        'billing.checkout_hold_minutes' => 30,
        'billing.renewal_window_days' => 30,
        'sponsorship.enabled' => true,
        'sponsorship.pass_price_tender_minor' => 20_000,
        'sponsorship.pass_price_auction_minor' => 20_000,
    ],

];
