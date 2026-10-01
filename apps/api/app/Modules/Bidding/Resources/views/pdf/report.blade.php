{{-- Result report (ARCHITECTURE §7.14), issuer variant. Rendered by App\Modules\Bidding\Services\ReportGenerator. --}}
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('bidding.report.title') }} {{ $competition->reference_no }}</title>
    <style>
        body { font-size: 10pt; color: #1F2A24; }
        h1 { font-size: 18pt; color: #0B7A55; margin: 0 0 4pt 0; }
        h2 { font-size: 12pt; color: #0B7A55; border-bottom: 1px solid #CFE3D9; padding-bottom: 2pt; margin: 14pt 0 6pt 0; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #EEF6F2; font-weight: bold; text-align: {{ $direction === 'rtl' ? 'right' : 'left' }}; }
        th, td { padding: 3pt 4pt; border-bottom: 1px solid #E3ECE7; vertical-align: top; }
        .meta td { border: none; padding: 1pt 4pt; }
        .muted { color: #6B7B72; }
        .chip { background: #0B7A55; color: #FFFFFF; padding: 1pt 6pt; }
        .voided { color: #B42318; text-decoration: line-through; }
        .hash { font-family: monospace; font-size: 8pt; }
        ul { margin: 0; padding: 0 12pt; }
    </style>
</head>
<body>
<htmlpagefooter name="report-footer">
    <table class="meta"><tr>
        <td class="muted">{{ $competition->reference_no }} · {{ __('bidding.report.generated_at') }} {{ $generatedAt }}</td>
        <td class="muted" style="text-align: {{ $direction === 'rtl' ? 'left' : 'right' }};">{{ __('bidding.report.page') }} {PAGENO}/{nbpg}</td>
    </tr></table>
</htmlpagefooter>
<sethtmlpagefooter name="report-footer" value="on" />

{{-- 1. Cover --}}
<h1>{{ __('bidding.report.title') }}</h1>
<p><strong>{{ $competition->title }}</strong></p>
<table class="meta">
    <tr><td class="muted">{{ __('bidding.report.reference') }}</td><td>{{ $competition->reference_no }}</td></tr>
    <tr><td class="muted">{{ __('bidding.report.issuer') }}</td><td>{{ $issuerName }}</td></tr>
    <tr><td class="muted">{{ __('bidding.report.type') }}</td><td><span class="chip">{{ $competition->direction->label($locale) }}</span></td></tr>
    <tr><td class="muted">{{ __('bidding.report.format') }}</td><td>{{ $competition->format->label($locale) }}</td></tr>
    <tr><td class="muted">{{ __('bidding.report.category') }}</td><td>{{ $categoryName }}</td></tr>
    <tr><td class="muted">{{ __('bidding.report.region') }}</td><td>{{ $regionName }}</td></tr>
    <tr><td class="muted">{{ __('bidding.report.status') }}</td><td>{{ $competition->status->label($locale) }}</td></tr>
    <tr><td class="muted">{{ __('bidding.report.generated_at') }}</td><td>{{ $generatedAt }}</td></tr>
</table>

{{-- 2. Rules summary --}}
<h2>{{ __('bidding.report.sections.rules') }}</h2>
<ul>
    @foreach ($rules as $line)
        <li>{{ $line }}</li>
    @endforeach
</ul>

{{-- 3. Timeline --}}
<h2>{{ __('bidding.report.sections.timeline') }}</h2>
<table>
    @foreach ($timeline as $event)
        <tr><td style="width: 38%;">{{ $event['at'] }}</td><td>{{ $event['label'] }}</td></tr>
    @endforeach
</table>

{{-- 4. Participants --}}
<h2>{{ __('bidding.report.sections.participants') }}</h2>
<table>
    <tr><th>{{ __('bidding.report.columns.alias') }}</th><th>{{ __('bidding.report.columns.name') }}</th><th>{{ __('bidding.report.columns.joined_at') }}</th></tr>
    @forelse ($participants as $participant)
        <tr><td>{{ __('bidding.report.participant_alias', ['alias' => $participant['alias_no']]) }}</td><td>{{ $participant['name'] }}</td><td>{{ $participant['joined_at'] }}</td></tr>
    @empty
        <tr><td colspan="3" class="muted">{{ __('bidding.report.empty') }}</td></tr>
    @endforelse
</table>

{{-- 5. Final ranking --}}
<h2>{{ __('bidding.report.sections.ranking') }}</h2>
<table>
    <tr>
        <th>{{ __('bidding.report.columns.rank') }}</th>
        <th>{{ __('bidding.report.columns.name') }}</th>
        <th>{{ __('bidding.report.columns.final_amount') }}</th>
        <th>{{ __('bidding.report.columns.reached_at') }}</th>
        <th>{{ __('bidding.report.columns.change_ratio') }}</th>
        <th>{{ __('bidding.report.columns.bafo_offer') }}</th>
    </tr>
    @forelse ($ranking as $row)
        <tr>
            <td>{{ $row['rank'] ?? '—' }}</td>
            <td>{{ __('bidding.report.participant_alias', ['alias' => $row['alias_no']]) }} · {{ $row['name'] }}</td>
            <td>{{ $row['amount'] }}</td>
            <td>{{ $row['reached_at'] }}</td>
            <td>{{ $row['change_ratio'] }}</td>
            <td>{{ $row['bafo'] }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">{{ __('bidding.report.empty') }}</td></tr>
    @endforelse
</table>

{{-- 6. Full offer log --}}
<h2>{{ __('bidding.report.sections.offer_log') }}</h2>
<table>
    <tr>
        <th>{{ __('bidding.report.columns.seq') }}</th>
        <th>{{ __('bidding.report.columns.time') }}</th>
        <th>{{ __('bidding.report.columns.participant') }}</th>
        <th>{{ __('bidding.report.columns.amount') }}</th>
        <th>{{ __('bidding.report.columns.stage') }}</th>
    </tr>
    @forelse ($offers as $offer)
        <tr class="{{ $offer['voided'] ? 'voided' : '' }}">
            <td>{{ $offer['seq'] }}</td>
            <td>{{ $offer['at'] }}</td>
            <td>{{ $offer['participant'] }}</td>
            <td>{{ $offer['amount'] }}</td>
            <td>{{ $offer['stage'] }}@if ($offer['voided']) · {{ __('bidding.report.voided') }}@endif</td>
        </tr>
    @empty
        <tr><td colspan="5" class="muted">{{ __('bidding.report.empty') }}</td></tr>
    @endforelse
</table>

{{-- 7. Metrics --}}
<h2>{{ __('bidding.report.sections.metrics') }}</h2>
<table class="meta">
    <tr><td class="muted">{{ __('bidding.report.metrics.leader_amount') }}</td><td>{{ $metrics['leader_amount'] }}</td></tr>
    <tr><td class="muted">{{ __('bidding.report.metrics.improvement.'.$competition->direction->value) }}</td><td>{{ $metrics['improvement'] }}</td></tr>
    <tr><td class="muted">{{ __('bidding.report.metrics.offers_count') }}</td><td>{{ $metrics['offers_count'] }}</td></tr>
    <tr><td class="muted">{{ __('bidding.report.metrics.participants_joined') }}</td><td>{{ $metrics['participants_joined'] }}</td></tr>
    <tr><td class="muted">{{ __('bidding.report.metrics.participants_with_offers') }}</td><td>{{ $metrics['participants_with_offers'] }}</td></tr>
    <tr><td class="muted">{{ __('bidding.report.metrics.invitations_count') }}</td><td>{{ $metrics['invitations_count'] }}</td></tr>
    <tr><td class="muted">{{ __('bidding.report.metrics.extension_count') }}</td><td>{{ $metrics['extension_count'] }}</td></tr>
</table>

{{-- 8. Award --}}
<h2>{{ __('bidding.report.sections.award') }}</h2>
@if ($award === null)
    <p class="muted">{{ __('bidding.report.no_award') }}</p>
@else
    <table class="meta">
        <tr><td class="muted">{{ __('bidding.report.award.awardee') }}</td><td>{{ __('bidding.report.participant_alias', ['alias' => $award['alias_no']]) }} · {{ $award['name'] }}</td></tr>
        <tr><td class="muted">{{ __('bidding.report.award.amount') }}</td><td>{{ $award['amount'] }}</td></tr>
        <tr><td class="muted">{{ __('bidding.report.award.leading') }}</td><td>{{ $award['leading'] ? __('bidding.report.yes') : __('bidding.report.no') }}</td></tr>
        @if ($award['justification'] !== null)
            <tr><td class="muted">{{ __('bidding.report.award.justification') }}</td><td>{{ $award['justification'] }}@if ($award['justification_text']) · {{ $award['justification_text'] }}@endif</td></tr>
        @endif
        @if ($award['internal_notes'])
            <tr><td class="muted">{{ __('bidding.report.award.internal_notes') }}</td><td>{{ $award['internal_notes'] }}</td></tr>
        @endif
        <tr><td class="muted">{{ __('bidding.report.award.awarded_at') }}</td><td>{{ $award['awarded_at'] }}@if ($award['awarded_by']) · {{ $award['awarded_by'] }}@endif</td></tr>
    </table>
@endif

{{-- 9. Integrity --}}
<h2>{{ __('bidding.report.sections.integrity') }}</h2>
<table class="meta">
    <tr><td class="muted">{{ __('bidding.report.integrity.ledger_head_hash') }}</td><td class="hash">{{ $ledgerHeadHash ?? '—' }}</td></tr>
    <tr><td class="muted">{{ __('bidding.report.generated_at') }}</td><td>{{ $generatedAt }}</td></tr>
</table>
<p class="muted">{{ __('bidding.report.integrity.note') }}</p>
<p class="muted">{{ __('bidding.report.prices_excl_vat') }}</p>
</body>
</html>
