<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Exports;

use App\Modules\Bidding\Enums\AwardStatus;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Services\VisibilityProjector;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Integrations\Enums\ExportType;
use App\Modules\Integrations\Models\ExportJob;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Services\ExternalRefs;
use App\Modules\Integrations\Services\Imports\VendorColumns;
use App\Modules\Integrations\Services\VendorDirectory;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The rows of a dashboard export (ARCHITECTURE §14.8), always in the issuer projection (the
 * Bidding VisibilityProjector decides, §7.9): in a sealed competition before the offers are
 * opened, amounts, ranks and the leader flag are empty.
 *
 *   - Amounts are decimal SAR with two decimals; their headers carry "(SAR, excl. VAT)".
 *   - Times are Asia/Riyadh, `yyyy-MM-dd HH:mm:ss.SSS`, headers with "(KSA)" (CONVENTIONS §9.1).
 *   - The vendors export uses exactly the import columns (plus `linked`), so it can be re-imported.
 *
 * CONTRACT-GAP: §14.8 names the columns only. Every time column is in Asia/Riyadh with a "(KSA)"
 * header, `vat_rate` is written as "15%", booleans as Y/N, and the awards `from` / `to` dates are
 * Riyadh calendar days (inclusive).
 */
final readonly class ExportBuilder
{
    public const string TIMEZONE = 'Asia/Riyadh';

    public function __construct(
        private VendorDirectory $vendors,
        private VisibilityProjector $projector,
    ) {}

    public function build(ExportJob $job): ExportTable
    {
        return match ($job->type) {
            ExportType::Results => $this->results($job),
            ExportType::OfferLog => $this->offerLog($job),
            ExportType::Awards => $this->awards($job),
            ExportType::Vendors => $this->vendorsTable($job),
        };
    }

    public static function sar(?int $minor): string
    {
        return $minor === null ? '' : sprintf('%d.%02d', intdiv($minor, 100), $minor % 100);
    }

    public static function time(?CarbonInterface $time): string
    {
        return $time === null ? '' : $time->copy()->setTimezone(self::TIMEZONE)->format('Y-m-d H:i:s.v');
    }

    private function results(ExportJob $job): ExportTable
    {
        $competition = $this->competition($job);
        $hidden = $this->projector->amountsHiddenFromIssuer($competition);
        $awarded = Award::query()
            ->where('competition_id', $competition->id)
            ->where('status', AwardStatus::Issued)
            ->pluck('participant_id')
            ->all();

        $participants = Participant::query()
            ->with(['organization', 'invitation', 'standing'])
            ->where('competition_id', $competition->id)
            ->get()
            ->sortBy(static fn (Participant $p): array => [$p->standing->rank ?? PHP_INT_MAX, $p->alias_no])
            ->values();

        $rows = [];

        foreach ($participants as $participant) {
            $standing = $participant->standing;
            $vendor = $this->vendors->forCounterparty($competition->organization_id, $participant->invitation?->vendor_id, $participant->organization_id);

            $rows[] = [
                $competition->reference_no,
                $competition->title,
                $competition->direction->value,
                $competition->format->value,
                self::time($competition->closed_at),
                $participant->organization?->name,
                $participant->organization?->cr_number,
                $participant->organization?->vat_number,
                self::supplierRef($vendor)?->value,
                $hidden ? '' : ($standing->rank ?? ''),
                $hidden ? '' : self::sar($standing?->current_amount_minor),
                $hidden ? '' : self::sar($standing?->first_amount_minor),
                $standing->offers_count ?? 0,
                self::time($standing?->last_offer_at),
                $hidden ? '' : self::yesNo($standing->is_leader ?? false),
                self::yesNo(in_array($participant->id, $awarded, true)),
            ];
        }

        return new ExportTable('results-'.self::slug($competition), [
            'reference_no', 'title', 'direction', 'format', 'closed_at (KSA)', 'participant_name', 'cr_number', 'vat_number',
            'vendor_external_id', 'rank', 'current_amount (SAR, excl. VAT)', 'first_amount (SAR, excl. VAT)', 'offers_count',
            'last_offer_at (KSA)', 'is_leader', 'awarded',
        ], $rows);
    }

    private function offerLog(ExportJob $job): ExportTable
    {
        $competition = $this->competition($job);
        $hidden = $this->projector->amountsHiddenFromIssuer($competition);

        $offers = Offer::query()
            ->with(['organization', 'void'])
            ->where('competition_id', $competition->id)
            ->orderBy('seq')
            ->get();

        $rows = [];

        foreach ($offers as $offer) {
            $rows[] = [
                $competition->reference_no,
                $offer->seq,
                self::time($offer->accepted_at),
                $offer->organization?->name,
                $hidden ? '' : self::sar($offer->amount_minor),
                $offer->stage->value,
                self::yesNo($offer->void !== null),
            ];
        }

        return new ExportTable('offer-log-'.self::slug($competition), [
            'reference_no', 'seq', 'accepted_at (KSA)', 'participant_name', 'amount (SAR, excl. VAT)', 'stage', 'voided',
        ], $rows);
    }

    private function awards(ExportJob $job): ExportTable
    {
        $from = self::filterDate($job, 'from');
        $to = self::filterDate($job, 'to');
        $vatRate = (int) config('bafo.billing.vat_rate_bp', 1500);

        $awards = Award::query()
            ->with(['competition', 'organization', 'participant.invitation', 'justificationReason', 'externalRefs'])
            ->whereHas('competition', static fn (Builder $query) => $query->where('organization_id', $job->organization_id))
            ->when($from !== null, static fn (Builder $query) => $query->where('awarded_at', '>=', $from?->utc()))
            ->when($to !== null, static fn (Builder $query) => $query->where('awarded_at', '<', $to?->addDay()->utc()))
            ->orderBy('awarded_at')
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($awards as $award) {
            $competition = $award->competition;
            $vendor = $competition === null ? null : $this->vendors->forCounterparty(
                $competition->organization_id, $award->participant?->invitation?->vendor_id, $award->organization_id,
            );
            $supplier = self::supplierRef($vendor);
            $vat = Money::vat($award->amount_minor, $vatRate);
            $reason = $award->justificationReason;

            $rows[] = [
                $award->public_id,
                $competition?->reference_no,
                $competition?->title,
                $competition?->direction->value,
                self::time($award->awarded_at),
                $award->status->value,
                $award->organization?->name,
                $award->organization?->cr_number,
                $award->organization?->vat_number,
                $supplier?->system,
                $supplier?->value,
                self::sar($award->amount_minor),
                ($vatRate / 100).'%',
                self::sar($vat),
                self::sar($award->amount_minor + $vat),
                trim(implode(': ', array_filter([$reason?->translated('name'), $award->justification_text]))),
                $award->erp_sync_status->value,
                implode('; ', array_map(
                    static fn (array $ref): string => $ref['system'].':'.$ref['type'].':'.$ref['id'],
                    ExternalRefs::present($award->externalRefs),
                )),
            ];
        }

        return new ExportTable('awards', [
            'award_id', 'reference_no', 'title', 'direction', 'awarded_at (KSA)', 'status', 'winner_name', 'cr_number',
            'vat_number', 'vendor_external_system', 'vendor_external_id', 'amount (SAR, excl. VAT)', 'vat_rate',
            'vat_amount (SAR)', 'amount_incl_vat (SAR)', 'justification', 'erp_sync_status', 'erp_refs',
        ], $rows);
    }

    private function vendorsTable(ExportJob $job): ExportTable
    {
        $vendors = Vendor::query()
            ->with(['region', 'categories', 'externalRefs'])
            ->where('organization_id', $job->organization_id)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($vendors as $vendor) {
            $supplier = self::supplierRef($vendor);

            $rows[] = [
                $supplier?->system,
                $supplier?->value,
                $vendor->name,
                $vendor->name_en,
                $vendor->cr_number,
                $vendor->vat_number,
                $vendor->email,
                $vendor->contact_name,
                $vendor->phone,
                $vendor->region?->code,
                $vendor->city,
                $vendor->categories->pluck('code')->sort()->implode(VendorColumns::LIST_SEPARATOR),
                $vendor->status->value,
                self::yesNo($vendor->linked_organization_id !== null),
            ];
        }

        return new ExportTable('vendors', [...VendorColumns::ALL, 'linked'], $rows);
    }

    private function competition(ExportJob $job): Competition
    {
        $publicId = $job->filters['competition_id'] ?? null;

        return Competition::query()
            ->where('organization_id', $job->organization_id)
            ->wherePublicId(is_string($publicId) ? $publicId : '')
            ->firstOrFail();
    }

    private static function supplierRef(?Vendor $vendor): ?ExternalRef
    {
        return $vendor?->externalRefs
            ->filter(static fn (ExternalRef $ref): bool => $ref->type === ExternalRefs::TYPE_SUPPLIER)
            ->sortBy('id')
            ->first();
    }

    private static function filterDate(ExportJob $job, string $key): ?CarbonImmutable
    {
        $value = $job->filters[$key] ?? null;

        return is_string($value) ? CarbonImmutable::createFromFormat('Y-m-d', $value, self::TIMEZONE)?->startOfDay() : null;
    }

    private static function yesNo(bool $value): string
    {
        return $value ? 'Y' : 'N';
    }

    private static function slug(Competition $competition): string
    {
        return $competition->reference_no ?? $competition->public_id;
    }
}
