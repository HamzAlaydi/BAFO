<?php

declare(strict_types=1);

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Filament\Support\Display;
use App\Modules\Admin\Filament\Support\Fields;
use App\Modules\Admin\Filament\Support\Lang;
use App\Modules\Billing\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * §16 Payments "CSV export": the filtered payments list as UTF-8 CSV (with a BOM, so Excel reads
 * Arabic names), headers in the panel language, times in Asia/Riyadh, amounts in SAR with two
 * decimals.
 */
final class PaymentsCsv
{
    private const array COLUMNS = [
        'id', 'created_at', 'organization', 'purpose', 'status', 'gateway', 'gateway_reference',
        'subtotal', 'discount', 'credit', 'vat', 'total', 'currency', 'paid_at', 'failure_code',
        'refund_reference', 'manual_reference',
    ];

    /**
     * @param  Builder<Payment>  $query
     */
    public static function download(Builder $query): StreamedResponse
    {
        $filename = 'payments-'.CarbonImmutable::now(Display::TIMEZONE)->format('Ymd-His').'.csv';

        return response()->streamDownload(static function () use ($query): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(static fn (string $column): string => Lang::get('csv.payments.'.$column), self::COLUMNS), escape: '');

            $query->clone()->with('organization')->chunkById(500, static function ($payments) use ($out): void {
                foreach ($payments as $payment) {
                    /** @var Payment $payment */
                    fputcsv($out, self::row($payment), escape: '');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<string>
     */
    public static function row(Payment $payment): array
    {
        return [
            $payment->public_id,
            (string) Display::dateTime($payment->created_at, 'Y-m-d H:i:s'),
            self::text($payment->organization->name),
            $payment->purpose->value,
            $payment->status->value,
            $payment->gateway,
            self::text((string) $payment->gateway_reference),
            Fields::toDecimal($payment->subtotal_minor),
            Fields::toDecimal($payment->discount_minor),
            Fields::toDecimal($payment->credit_minor),
            Fields::toDecimal($payment->vat_minor),
            Fields::toDecimal($payment->total_minor),
            $payment->currency,
            (string) Display::dateTime($payment->paid_at, 'Y-m-d H:i:s'),
            self::text((string) $payment->failure_code),
            self::text((string) $payment->refund_reference),
            self::text((string) $payment->manual_reference),
        ];
    }

    /**
     * Free text typed by organizations or admins, written as text: a cell starting with a formula
     * trigger (= + - @, tab, carriage return) gets a leading `'` (SECURITY_REVIEW S-07).
     */
    private static function text(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
