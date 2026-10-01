<?php

declare(strict_types=1);

namespace App\Modules\Billing\Database\Factories;

use App\Modules\Billing\Enums\EInvoiceStatus;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Identity\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * The tax invoice of a succeeded SAR 1,500 + VAT payment, numbered from `invoice_number_seq`,
 * with seller (placeholder) and buyer snapshots; the e-invoice is pending.
 *
 * @extends Factory<Invoice>
 */
final class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $today = CarbonImmutable::now('Asia/Riyadh')->startOfDay();

        return [
            'number' => static fn (): string => sprintf(
                'BAFO-INV-%s-%06d',
                $today->format('Y'),
                (int) DB::scalar("select nextval('invoice_number_seq')"),
            ),
            'payment_id' => Payment::factory()->succeeded(),
            'organization_id' => static fn (array $attributes): int => Payment::query()
                ->findOrFail($attributes['payment_id'])
                ->organization_id,
            'type' => InvoiceType::TaxInvoice,
            'original_invoice_id' => null,
            'issue_date' => $today,
            'supply_date' => $today,
            'currency' => 'SAR',
            'subtotal_minor' => static fn (array $attributes): int => Payment::query()->findOrFail($attributes['payment_id'])->subtotal_minor,
            'discount_minor' => 0,
            'vat_minor' => static fn (array $attributes): int => Payment::query()->findOrFail($attributes['payment_id'])->vat_minor,
            'total_minor' => static fn (array $attributes): int => Payment::query()->findOrFail($attributes['payment_id'])->total_minor,
            'vat_rate_bp' => 1500,
            'seller_snapshot' => [
                'name_ar' => 'بافو (نص مؤقت)',
                'name_en' => 'BAFO (placeholder)',
                'vat_number' => '300000000000003',
                'cr_number' => '0000000000',
                'address' => 'الرياض، المملكة العربية السعودية',
            ],
            'buyer_snapshot' => static function (array $attributes): array {
                $buyer = Organization::query()->findOrFail($attributes['organization_id']);

                return [
                    'legal_name_ar' => $buyer->legal_name_ar,
                    'legal_name_en' => $buyer->legal_name_en,
                    'cr_number' => $buyer->cr_number,
                    'vat_number' => $buyer->vat_number,
                    'city' => $buyer->city,
                    'building_number' => $buyer->address_building_number,
                    'street' => $buyer->address_street,
                    'district' => $buyer->address_district,
                    'postal_code' => $buyer->address_postal_code,
                ];
            },
            'einvoice_provider' => 'fake',
            'einvoice_status' => EInvoiceStatus::Pending,
            'einvoice_attempts' => 0,
            'issued_at' => now(),
        ];
    }

    public function cleared(): self
    {
        return $this->state(fn (): array => [
            'einvoice_status' => EInvoiceStatus::Cleared,
            'einvoice_document_id' => 'fake-'.$this->faker->uuid(),
            'zatca_uuid' => $this->faker->uuid(),
            'einvoice_attempts' => 1,
            'cleared_at' => now(),
        ]);
    }
}
