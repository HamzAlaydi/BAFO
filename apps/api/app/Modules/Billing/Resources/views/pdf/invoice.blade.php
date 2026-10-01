{{-- Tax invoice PDF (ARCHITECTURE §13.7): Arabic and English side by side, rendered by PdfRenderer (mpdf). --}}
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-size: 10pt; color: #1F2933; }
        h1 { color: #0B7A55; font-size: 16pt; margin: 0 0 4pt 0; }
        .en { direction: ltr; text-align: left; }
        .ar { direction: rtl; text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        .grid td { vertical-align: top; padding: 4pt; }
        .lines th { background: #E6F7F1; color: #0B7A55; padding: 5pt; font-size: 9pt; }
        .lines td { border-bottom: 0.5pt solid #CBD2D9; padding: 5pt; }
        .num { direction: ltr; text-align: center; white-space: nowrap; }
        .totals td { padding: 4pt; }
        .totals .label-ar { text-align: right; }
        .totals .label-en { text-align: left; direction: ltr; }
        .totals .grand td { font-weight: bold; border-top: 1pt solid #0B7A55; }
        .muted { color: #52606D; font-size: 8.5pt; }
        .qr { text-align: center; padding-top: 10pt; }
    </style>
</head>
<body>
<table class="grid">
    <tr>
        <td class="ar" width="50%">
            <h1>{{ __('billing.invoice.title', [], 'ar') }}</h1>
            <div>{{ __('billing.invoice.number', [], 'ar') }}: <bdi>{{ $invoice->number }}</bdi></div>
            <div>{{ __('billing.invoice.issue_date', [], 'ar') }}: <bdi>{{ $invoice->issue_date->format('Y-m-d') }}</bdi></div>
            <div>{{ __('billing.invoice.supply_date', [], 'ar') }}: <bdi>{{ $invoice->supply_date->format('Y-m-d') }}</bdi></div>
        </td>
        <td class="en" width="50%">
            <h1>{{ __('billing.invoice.title', [], 'en') }}</h1>
            <div>{{ __('billing.invoice.number', [], 'en') }}: {{ $invoice->number }}</div>
            <div>{{ __('billing.invoice.issue_date', [], 'en') }}: {{ $invoice->issue_date->format('Y-m-d') }}</div>
            <div>{{ __('billing.invoice.issued_at', [], 'en') }}: {{ $issued_at }} (KSA)</div>
        </td>
    </tr>
    <tr>
        <td class="ar">
            <strong>{{ __('billing.invoice.seller', [], 'ar') }}</strong><br>
            {{ $seller['name_ar'] ?? '' }}<br>
            {{ __('billing.invoice.vat_number', [], 'ar') }}: <bdi>{{ $seller['vat_number'] ?? '' }}</bdi><br>
            {{ __('billing.invoice.cr_number', [], 'ar') }}: <bdi>{{ $seller['cr_number'] ?? '' }}</bdi><br>
            <span class="muted">{{ $seller['address'] ?? '' }}</span>
        </td>
        <td class="en">
            <strong>{{ __('billing.invoice.seller', [], 'en') }}</strong><br>
            {{ $seller['name_en'] ?? '' }}<br>
            {{ __('billing.invoice.vat_number', [], 'en') }}: {{ $seller['vat_number'] ?? '' }}<br>
            {{ __('billing.invoice.cr_number', [], 'en') }}: {{ $seller['cr_number'] ?? '' }}
        </td>
    </tr>
    <tr>
        <td class="ar">
            <strong>{{ __('billing.invoice.buyer', [], 'ar') }}</strong><br>
            {{ $buyer['legal_name_ar'] ?? $buyer['name'] ?? '' }}<br>
            @if (! empty($buyer['vat_number']))
                {{ __('billing.invoice.vat_number', [], 'ar') }}: <bdi>{{ $buyer['vat_number'] }}</bdi><br>
            @endif
            {{ __('billing.invoice.cr_number', [], 'ar') }}: <bdi>{{ $buyer['cr_number'] ?? '' }}</bdi><br>
            <span class="muted">{{ trim(($buyer['building_number'] ?? '').' '.($buyer['street'] ?? '').'، '.($buyer['district'] ?? '').'، '.($buyer['city'] ?? '').' '.($buyer['postal_code'] ?? '')) }}</span>
        </td>
        <td class="en">
            <strong>{{ __('billing.invoice.buyer', [], 'en') }}</strong><br>
            {{ $buyer['legal_name_en'] ?? $buyer['name'] ?? '' }}<br>
            @if (! empty($buyer['vat_number']))
                {{ __('billing.invoice.vat_number', [], 'en') }}: {{ $buyer['vat_number'] }}<br>
            @endif
            {{ __('billing.invoice.cr_number', [], 'en') }}: {{ $buyer['cr_number'] ?? '' }}
        </td>
    </tr>
</table>

<br>
<table class="lines">
    <thead>
    <tr>
        <th class="ar">{{ __('billing.invoice.description', [], 'ar') }} / {{ __('billing.invoice.description', [], 'en') }}</th>
        <th>{{ __('billing.invoice.quantity', [], 'ar') }} / {{ __('billing.invoice.quantity', [], 'en') }}</th>
        <th>{{ __('billing.invoice.unit_price', [], 'ar') }} / {{ __('billing.invoice.unit_price', [], 'en') }}</th>
        <th>{{ __('billing.invoice.net', [], 'ar') }} / {{ __('billing.invoice.net', [], 'en') }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($lines as $line)
        <tr>
            <td class="ar">{{ $line['description_ar'] }}<br><span class="en muted">{{ $line['description_en'] }}</span></td>
            <td class="num"><bdi>{{ $line['quantity'] }}</bdi></td>
            <td class="num"><bdi>{{ $line['unit_price']['en'] }}</bdi></td>
            <td class="num"><bdi>{{ $line['net']['en'] }}</bdi></td>
        </tr>
    @endforeach
    </tbody>
</table>

<br>
<table class="totals">
    <tr>
        <td class="label-ar">{{ __('billing.invoice.subtotal', [], 'ar') }}</td>
        <td class="num"><bdi>{{ $amounts['subtotal']['en'] }}</bdi></td>
        <td class="label-en">{{ __('billing.invoice.subtotal', [], 'en') }}</td>
    </tr>
    <tr>
        <td class="label-ar">{{ __('billing.invoice.discount', [], 'ar') }}</td>
        <td class="num"><bdi>{{ $amounts['discount']['en'] }}</bdi></td>
        <td class="label-en">{{ __('billing.invoice.discount', [], 'en') }}</td>
    </tr>
    <tr>
        <td class="label-ar">{{ __('billing.invoice.taxable', [], 'ar') }}</td>
        <td class="num"><bdi>{{ $amounts['taxable']['en'] }}</bdi></td>
        <td class="label-en">{{ __('billing.invoice.taxable', [], 'en') }}</td>
    </tr>
    <tr>
        <td class="label-ar">{{ __('billing.invoice.vat', ['rate' => $vat_percent], 'ar') }}</td>
        <td class="num"><bdi>{{ $amounts['vat']['en'] }}</bdi></td>
        <td class="label-en">{{ __('billing.invoice.vat', ['rate' => $vat_percent], 'en') }}</td>
    </tr>
    <tr class="grand">
        <td class="label-ar">{{ __('billing.invoice.total', [], 'ar') }}</td>
        <td class="num"><bdi>{{ $amounts['total']['en'] }}</bdi></td>
        <td class="label-en">{{ __('billing.invoice.total', [], 'en') }}</td>
    </tr>
</table>

@if ($qr_image)
    <div class="qr">
        <img src="{{ $qr_image }}" width="110" height="110" alt="ZATCA QR">
    </div>
@endif
@if ($invoice->zatca_uuid)
    <p class="muted en">UUID: {{ $invoice->zatca_uuid }}</p>
@endif
</body>
</html>
