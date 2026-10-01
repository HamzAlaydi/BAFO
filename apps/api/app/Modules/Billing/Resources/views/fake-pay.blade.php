{{-- The fake gateway's hosted checkout page (ARCHITECTURE §13.6). Local and test environments only. --}}
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('billing.fake_pay.title') }}</title>
    <style>
        :root { --brand: #0B7A55; --brand-soft: #E6F7F1; --ink: #1F2933; --muted: #52606D; --line: #E4E7EB; --danger: #B42318; --bg: #F5F7FA; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font-family: "IBM Plex Sans Arabic", "Inter", system-ui, sans-serif; line-height: 1.5; }
        main { max-width: 32rem; margin: 2rem auto; padding: 0 1rem; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 1.5rem; }
        .merchant { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; margin-bottom: 1rem; }
        .merchant strong { color: var(--brand); font-size: 1.5rem; letter-spacing: .02em; }
        .badge { background: var(--brand-soft); color: var(--brand); border-radius: 999px; padding: .15rem .75rem; font-size: .8rem; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        td { padding: .5rem 0; border-bottom: 1px solid var(--line); vertical-align: top; }
        td.amount { text-align: end; white-space: nowrap; }
        tr.total td { font-weight: 700; border-bottom: 0; font-size: 1.1rem; }
        .muted { color: var(--muted); font-size: .9rem; }
        .notice { background: var(--brand-soft); border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; }
        .actions { display: flex; gap: .75rem; margin-top: 1.25rem; }
        .actions form { flex: 1; }
        button { width: 100%; border: 0; border-radius: 8px; padding: .8rem 1rem; font: inherit; font-weight: 600; cursor: pointer; }
        button:focus-visible { outline: 3px solid #1D4ED8; outline-offset: 2px; }
        .approve { background: var(--brand); color: #fff; }
        .decline { background: #fff; color: var(--danger); border: 1px solid var(--danger); }
    </style>
</head>
<body>
<main>
    <div class="card">
        <div class="merchant">
            <strong>BAFO</strong>
            <span class="badge">{{ __('billing.fake_pay.test_mode') }}</span>
        </div>
        <p class="muted">{{ __('billing.fake_pay.merchant') }}</p>

        @if ($conflict)
            <p class="notice" role="status">{{ __('billing.fake_pay.not_pending', ['status' => $payment->status->label()]) }}</p>
        @endif

        <table>
            <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line['description'] }} <span class="muted">× <bdi>{{ $line['quantity'] }}</bdi></span></td>
                    <td class="amount"><bdi>{{ $line['net'] }}</bdi></td>
                </tr>
            @endforeach
            <tr>
                <td>{{ __('billing.fake_pay.subtotal') }}</td>
                <td class="amount"><bdi>{{ $amounts['subtotal'] }}</bdi></td>
            </tr>
            @if ($amounts['credit'] !== null)
                <tr>
                    <td>{{ __('billing.fake_pay.credit') }}</td>
                    <td class="amount">− <bdi>{{ $amounts['credit'] }}</bdi></td>
                </tr>
            @endif
            @if ($amounts['discount'] !== null)
                <tr>
                    <td>{{ __('billing.fake_pay.discount') }}@if ($payment->coupon) <span class="muted">(<bdi>{{ $payment->coupon->code }}</bdi>)</span>@endif</td>
                    <td class="amount">− <bdi>{{ $amounts['discount'] }}</bdi></td>
                </tr>
            @endif
            <tr>
                <td>{{ __('billing.fake_pay.vat', ['rate' => $vatPercent]) }}</td>
                <td class="amount"><bdi>{{ $amounts['vat'] }}</bdi></td>
            </tr>
            <tr class="total">
                <td>{{ __('billing.fake_pay.total') }}</td>
                <td class="amount"><bdi>{{ $amounts['total'] }}</bdi></td>
            </tr>
            </tbody>
        </table>

        @if ($payment->isPending())
            <div class="actions">
                <form method="post" action="{{ url('/pay/fake/'.$payment->public_id.'/approve') }}" id="approve-form">
                    @csrf
                    <button type="submit" class="approve">{{ __('billing.fake_pay.approve') }}</button>
                </form>
                <form method="post" action="{{ url('/pay/fake/'.$payment->public_id.'/decline') }}">
                    @csrf
                    <button type="submit" class="decline">{{ __('billing.fake_pay.decline') }}</button>
                </form>
            </div>
        @endif

        <p class="muted">{{ __('billing.fake_pay.hint') }}</p>
    </div>
</main>
@if ($autoApprove)
    <script>setTimeout(function () { document.getElementById('approve-form').submit(); }, 1000);</script>
@endif
</body>
</html>
