<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Services;

use App\Modules\Bidding\Enums\AwardStatus;
use App\Modules\Bidding\Enums\ReportStatus;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Bidding\Models\CompetitionReport;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Competitions\Services\RulesSummary;
use App\Support\Files\File;
use App\Support\Files\FilePurpose;
use App\Support\Files\FileStorage;
use App\Support\Http\Iso;
use App\Support\Money\Money;
use App\Support\Pdf\PdfRenderer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The result report PDF (ARCHITECTURE §7.14), issuer variant only: cover, rules summary,
 * timeline, participants, final ranking, full offer log (voids marked), metrics, award and
 * integrity (ledger head hash, generated at, page x/y). Rendered with `bidding::pdf.report`
 * through PdfRenderer, stored with purpose `competition_report` for the issuer organization,
 * and upserted into `competition_reports` with the rendered live-state version.
 */
final readonly class ReportGenerator
{
    public const string VIEW = 'bidding::pdf.report';

    public function __construct(
        private PdfRenderer $pdf,
        private FileStorage $files,
        private LiveStateManager $liveStates,
        private VisibilityProjector $projector,
    ) {}

    public function generate(Competition $competition, string $locale): CompetitionReport
    {
        $version = $this->liveStates->read($competition)->version;

        try {
            $bytes = $this->pdf->render(self::VIEW, $this->data($competition, $locale), $locale);

            $file = $this->files->storeContents(
                $bytes,
                ($competition->reference_no ?? $competition->public_id).'-'.$locale.'.pdf',
                'application/pdf',
                FilePurpose::CompetitionReport,
                $competition->organization_id,
            );
        } catch (Throwable $exception) {
            $this->markFailed($competition, $locale, $version);

            throw $exception;
        }

        return DB::transaction(function () use ($competition, $locale, $version, $file): CompetitionReport {
            $report = CompetitionReport::query()
                ->where('competition_id', $competition->id)
                ->where('locale', $locale)
                ->lockForUpdate()
                ->first() ?? new CompetitionReport(['competition_id' => $competition->id, 'locale' => $locale]);

            $previousFileId = $report->file_id;

            $report->fill([
                'status' => ReportStatus::Ready,
                'file_id' => $file->id,
                'live_version' => $version,
                'generated_at' => Date::now(),
            ])->save();

            if ($previousFileId !== null && $previousFileId !== $file->id) {
                $previous = File::query()->find($previousFileId);

                if ($previous !== null) {
                    $this->files->delete($previous);
                }
            }

            return $report;
        });
    }

    public function markFailed(Competition $competition, string $locale, int $version): void
    {
        CompetitionReport::query()->updateOrCreate(
            ['competition_id' => $competition->id, 'locale' => $locale],
            ['status' => ReportStatus::Failed, 'live_version' => $version],
        );
    }

    /**
     * The view data. Amounts are issuer-projected (null while sealed and locked).
     *
     * @return array<string, mixed>
     */
    public function data(Competition $competition, string $locale): array
    {
        // The labels built here (timeline, aliases, stages) must be in the report's language,
        // whatever the locale of the job or request that renders it.
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            return $this->buildData($competition, $locale);
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildData(Competition $competition, string $locale): array
    {
        $competition->loadMissing(['organization', 'category', 'region']);
        $hidden = $this->projector->amountsHiddenFromIssuer($competition);
        $state = $this->liveStates->read($competition);

        $participants = Participant::query()
            ->where('competition_id', $competition->id)
            ->with('organization')
            ->orderBy('alias_no')
            ->get()
            ->keyBy('id');

        $standings = ParticipantStanding::query()
            ->where('competition_id', $competition->id)
            ->with('bafoOffer')
            ->get()
            ->keyBy('participant_id');

        $offers = Offer::query()
            ->where('competition_id', $competition->id)
            ->withExists('void')
            ->orderBy('seq')
            ->get();

        $round = BafoRound::query()->where('competition_id', $competition->id)->first();
        $awards = Award::query()
            ->where('competition_id', $competition->id)
            ->with(['participant.organization', 'justificationReason', 'awardedBy'])
            ->orderBy('id')
            ->get();
        $award = $awards->first(static fn (Award $a): bool => $a->status === AwardStatus::Issued);

        $money = static fn (?int $amount): string => $amount === null ? '—' : Money::format($amount, $locale);
        $time = static fn (?CarbonInterface $at): string => $at === null ? '—' : $at->copy()->setTimezone('Asia/Riyadh')->format('Y-m-d H:i:s.v').' (KSA)';

        $ranking = $participants
            ->map(function (Participant $participant) use ($standings, $competition, $hidden, $money, $time): array {
                $standing = $standings->get($participant->id);

                return [
                    'rank' => $hidden ? null : $standing?->rank,
                    'alias_no' => $participant->alias_no,
                    'name' => $participant->organization->name,
                    'amount' => $money($hidden ? null : $standing?->current_amount_minor),
                    'reached_at' => $time($standing?->current_at),
                    'change_ratio' => $hidden ? '—' : self::percent(OfferRules::improvementBps($competition, $standing?->current_amount_minor, $standing?->first_amount_minor)),
                    'bafo' => $money($hidden ? null : $standing?->bafoOffer?->amount_minor),
                    'offers_count' => $standing->offers_count ?? 0,
                ];
            })
            ->sortBy(static fn (array $row): array => [$row['rank'] ?? PHP_INT_MAX, $row['alias_no']])
            ->values()
            ->all();

        return [
            'competition' => $competition,
            'issuerName' => $competition->organization->name,
            'categoryName' => $competition->category->translated('name', $locale),
            'regionName' => $competition->region->translated('name', $locale),
            'generatedAt' => $time(Date::now()),
            'rules' => $this->rulesSummary($competition, $locale),
            'timeline' => $this->timeline($competition, $round, $awards->all(), $time),
            'participants' => $participants->map(static fn (Participant $p): array => [
                'alias_no' => $p->alias_no,
                'name' => $p->organization->name,
                'joined_at' => $time($p->created_at),
            ])->values()->all(),
            'ranking' => $ranking,
            'offers' => $offers->map(fn (Offer $offer): array => [
                'seq' => $offer->seq,
                'at' => $time($offer->accepted_at),
                'participant' => __('bidding.report.participant_alias', ['alias' => $participants->get($offer->participant_id)->alias_no ?? '?'])
                    .' · '.($participants->get($offer->participant_id)?->organization->name ?? ''),
                'amount' => $money($this->projector->issuerOfferAmount($offer, $competition)),
                'stage' => $offer->stage->label($locale),
                'voided' => (bool) $offer->getAttribute('void_exists'),
            ])->all(),
            'metrics' => [
                'improvement' => $hidden ? '—' : self::percent(OfferRules::improvementBps($competition, $state->leader_amount_minor, $competition->start_price_minor)),
                'leader_amount' => $money($hidden ? null : $state->leader_amount_minor),
                'offers_count' => $state->accepted_offer_count,
                'participants_joined' => $participants->count(),
                'participants_with_offers' => $state->participants_with_offers,
                'invitations_count' => $competition->invitations()->count(),
                'extension_count' => $competition->extension_count,
            ],
            'award' => $award === null ? null : [
                'name' => $award->participant->organization->name,
                'alias_no' => $award->participant->alias_no,
                'amount' => $money($award->amount_minor),
                'leading' => $award->is_leading_offer,
                'justification' => $award->justificationReason?->translated('name', $locale),
                'justification_text' => $award->justification_text,
                'message_to_winner' => $award->message_to_winner,
                'internal_notes' => $award->internal_notes,
                'awarded_at' => $time($award->awarded_at),
                'awarded_by' => $award->awardedBy->name ?? null,
            ],
            'ledgerHeadHash' => $state->ledger_head_hash,
        ];
    }

    /**
     * @param  list<Award>  $awards
     * @param  callable(?CarbonInterface): string  $time
     * @return list<array{at: string, label: string, sort: string}>
     */
    private function timeline(Competition $competition, ?BafoRound $round, array $awards, callable $time): array
    {
        $events = [];
        $add = static function (?CarbonInterface $at, string $label) use (&$events, $time): void {
            if ($at !== null) {
                $events[] = ['at' => $time($at), 'label' => $label, 'sort' => Iso::format($at) ?? ''];
            }
        };

        $add($competition->published_at, __('bidding.report.timeline.published'));
        $add($competition->opened_at, __('bidding.report.timeline.opened'));
        $add($competition->final_window_started_at, __('bidding.report.timeline.final_window'));

        $extensions = CompetitionExtension::query()
            ->where('competition_id', $competition->id)
            ->with('triggeredByOffer')
            ->orderBy('id')
            ->get();

        foreach ($extensions as $extension) {
            $add($extension->created_at, __('bidding.report.timeline.extended', [
                'kind' => __('bidding.report.extension_kind.'.$extension->kind->value),
                'close' => $time($extension->new_close_at),
            ]).($extension->triggeredByOffer !== null ? ' · '.__('bidding.report.timeline.triggered_by', ['seq' => $extension->triggeredByOffer->seq]) : ''));
        }

        $add($competition->closed_at, __('bidding.report.timeline.closed'));
        $add($round?->starts_at, __('bidding.report.timeline.bafo_started'));
        $add($round?->ended_at, __('bidding.report.timeline.bafo_ended'));

        foreach ($awards as $award) {
            $add($award->awarded_at, __('bidding.report.timeline.awarded'));
            $add($award->revoked_at, __('bidding.report.timeline.award_revoked'));
        }

        $add($competition->not_awarded_at, __('bidding.report.timeline.not_awarded'));
        $add($competition->cancelled_at, __('bidding.report.timeline.cancelled'));

        usort($events, static fn (array $a, array $b): int => strcmp($a['sort'], $b['sort']));

        return $events;
    }

    /**
     * The issuer variant of the rules summary (ARCHITECTURE §7.16, Competitions `RulesSummary`).
     *
     * @return list<string>
     */
    private function rulesSummary(Competition $competition, string $locale): array
    {
        return RulesSummary::lines($competition, $locale, issuer: true);
    }

    private static function percent(?int $bps): string
    {
        if ($bps === null) {
            return '—';
        }

        return number_format($bps / 100, 2).'%';
    }
}
