<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Database\Seeders;

use App\Modules\Bidding\Actions\IssueAward;
use App\Modules\Bidding\Actions\StartBafoRound;
use App\Modules\Bidding\Actions\SubmitOffer;
use App\Modules\Billing\Actions\ConfigureSponsorship;
use App\Modules\Billing\Actions\GrantSponsoredPass;
use App\Modules\Billing\Enums\SponsorshipMode;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Actions\AddAttachment;
use App\Modules\Competitions\Actions\CancelCompetition;
use App\Modules\Competitions\Actions\CloseDueCompetition;
use App\Modules\Competitions\Actions\CloseWithoutAward;
use App\Modules\Competitions\Actions\CreateCompetition;
use App\Modules\Competitions\Actions\InviteParticipants;
use App\Modules\Competitions\Actions\JoinCompetition;
use App\Modules\Competitions\Actions\MarkInvitationViewed;
use App\Modules\Competitions\Actions\PostComment;
use App\Modules\Competitions\Actions\PublishCompetition;
use App\Modules\Competitions\Actions\StartFinalWindow;
use App\Modules\Competitions\Data\CompetitionInput;
use App\Modules\Competitions\Enums\AttachmentKind;
use App\Modules\Competitions\Models\Comment;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Competitions\Services\RulesMapper;
use App\Modules\Competitions\Services\ViewerResolver;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Auth\Actor;
use App\Support\Clock\DbClock;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Issuer Co's competitions in every status, tenders and auctions (ARCHITECTURE §17,
 * docs/build/DEMO.md), built through the Actions:
 *
 *   draft · scheduled · live tender (initial) · live tender (final window, offers) · live auction
 *   (show_prices, Buyer D bids) · live sealed · closed (offers) · bafo_round · awarded ·
 *   not_awarded (auction) · cancelled · sponsored live tender (Supplier B on a pass)
 *
 * The Actions run in real time; the published timeline is then aged (`age()`), so each
 * competition is in the wanted phase now. Offers, the BAFO round and the award use the Bidding
 * Actions (offers are timed by the database clock); the close of #7–#10 is then brought to now.
 * A scenario that fails (for example when another module is not seeded) is skipped with a
 * warning; the others still run.
 *
 * Titles are stable (`TITLES`) so that other demo seeders can find the competitions.
 */
final class CompetitionsDemoSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    public const array TITLES = [
        'draft' => 'توريد أجهزة حاسب محمول للإدارة العامة',
        'scheduled' => 'صيانة وتشغيل المباني الإدارية 2027',
        'live_initial' => 'توريد مستلزمات مكتبية للعام المالي 2027',
        'live_final_window' => 'توريد أجهزة ومستلزمات طبية للمستودع المركزي',
        'live_auction' => 'بيع معدات صناعية فائضة',
        'live_sealed' => 'تطوير بوابة إلكترونية لخدمات العملاء (عروض مغلقة)',
        'closed' => 'نقل وتخزين البضائع بين المستودعات',
        'bafo_round' => 'توريد خوادم ومعدات مركز البيانات',
        'awarded' => 'توريد أثاث مكتبي للمقر الرئيسي',
        'not_awarded' => 'بيع خردة حديد ومعادن',
        'cancelled' => 'خدمات تسويق وطباعة المواد الترويجية',
        'sponsored_live' => 'خدمات النظافة للفروع (رسوم مغطّاة)',
    ];

    private const int TENDER_CEILING = 25_000_000;

    /**
     * A minimal one-page PDF for the demo documents.
     */
    private const string DEMO_PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
        ."2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
        ."3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >> endobj\n"
        ."trailer << /Root 1 0 R >>\n%%EOF\n";

    private const int AUCTION_OPENING = 5_000_000;

    private CarbonImmutable $now;

    /**
     * @var array<string, Organization>
     */
    private array $organizations = [];

    /**
     * @var array<string, User>
     */
    private array $owners = [];

    public function run(): void
    {
        if (! $this->loadDemoParties()) {
            return;
        }

        $this->now = CarbonImmutable::now();

        foreach ([
            'draft' => fn () => $this->draftScenario(),
            'scheduled' => fn () => $this->scheduledScenario(),
            'live_initial' => fn () => $this->liveInitialScenario(),
            'live_final_window' => fn () => $this->liveFinalWindowScenario(),
            'live_auction' => fn () => $this->liveAuctionScenario(),
            'live_sealed' => fn () => $this->liveSealedScenario(),
            'closed' => fn () => $this->closedScenario(),
            'bafo_round' => fn () => $this->bafoScenario(),
            'awarded' => fn () => $this->awardedScenario(),
            'not_awarded' => fn () => $this->notAwardedScenario(),
            'cancelled' => fn () => $this->cancelledScenario(),
            'sponsored_live' => fn () => $this->sponsoredScenario(),
        ] as $key => $scenario) {
            try {
                $scenario();
            } catch (Throwable $e) {
                $this->warn("CompetitionsDemoSeeder: scenario [{$key}] skipped: ".$e->getMessage());
            }
        }
    }

    /**
     * Returns the competition of a scenario of `TITLES`, for other demo seeders.
     */
    public static function find(string $key): ?Competition
    {
        $title = self::TITLES[$key] ?? throw new RuntimeException("Unknown demo competition [{$key}].");

        return Competition::query()->where('title', $title)->latest('id')->first();
    }

    private function draftScenario(): void
    {
        $competition = $this->create('draft', opensAt: $this->now->addDays(3)->startOfHour(), closesAt: $this->now->addDays(10)->startOfHour());
        $this->invite($competition, ['supplier_a', 'supplier_c']);
    }

    private function scheduledScenario(): void
    {
        $competition = $this->create('scheduled', opensAt: $this->now->addDay()->startOfHour(), closesAt: $this->now->addDays(4)->startOfHour());
        $this->invite($competition, ['supplier_a', 'supplier_c', 'buyer_d']);
        $this->attachDocument($competition, AttachmentKind::InvitationDocument, 'scope-summary.pdf', 'نطاق العمل المختصر');

        $this->publish($competition);
        $this->view($competition, 'supplier_a');
    }

    private function liveInitialScenario(): void
    {
        $competition = $this->publish($this->create('live_initial', opensAt: null, closesAt: $this->now->addDays(3)->startOfHour()), [
            'supplier_a', 'supplier_c', 'buyer_d',
        ]);
        $this->attachDocument($competition, AttachmentKind::Document, 'specifications.pdf', 'كراسة الشروط والمواصفات');
        $this->attachLink($competition, 'https://docs.demo.bafo.test/office-supplies/catalogue', 'دليل الأصناف');
        $this->age($competition, minutes: 60);

        $this->join($competition, ['supplier_a', 'supplier_c']);

        $question = $this->comment($competition, 'supplier_a', 'هل تشمل الأسعار التوصيل إلى الفروع؟');
        $this->comment($competition, 'issuer', 'نعم، تشمل الأسعار التوصيل إلى جميع الفروع داخل الرياض.', $question);
        $this->comment($competition, 'supplier_c', 'هل يمكن تقديم بدائل للأصناف غير المتوفرة؟');
    }

    private function liveFinalWindowScenario(): void
    {
        // Published for 3h30, then aged by 3 hours: 30 minutes left, inside the 60-minute window.
        $competition = $this->publish($this->create('live_final_window', opensAt: null, closesAt: $this->now->addMinutes(210)), [
            'supplier_a', 'supplier_c', 'buyer_d',
        ]);
        $this->join($competition, ['supplier_a', 'supplier_c', 'buyer_d']);
        $this->age($competition, minutes: 180, includeClose: true);

        app(StartFinalWindow::class)->handle($competition->id);

        $this->offers($competition, ['supplier_a' => 24_000_000, 'supplier_c' => 23_800_000, 'buyer_d' => 23_900_000]);
    }

    private function liveAuctionScenario(): void
    {
        $competition = $this->publish($this->create('live_auction', opensAt: null, closesAt: $this->now->addDays(2)->startOfHour(), auction: true), [
            'buyer_d', 'supplier_a',
        ]);
        $this->join($competition, ['buyer_d', 'supplier_a']);
        $this->age($competition, minutes: 120);

        $this->offers($competition, ['buyer_d' => self::AUCTION_OPENING, 'supplier_a' => self::AUCTION_OPENING + 100_000]);
    }

    private function liveSealedScenario(): void
    {
        $competition = $this->publish($this->create('live_sealed', opensAt: null, closesAt: $this->now->addDay()->startOfHour(), sealed: true), [
            'supplier_a', 'supplier_c',
        ]);
        $this->join($competition, ['supplier_a', 'supplier_c']);
        $this->age($competition, minutes: 60);

        $this->offers($competition, ['supplier_a' => 17_500_000]);
    }

    private function closedScenario(): void
    {
        $competition = $this->liveWithParticipants('closed', ['supplier_a', 'supplier_c', 'buyer_d']);

        $this->offers($competition, ['supplier_a' => 24_500_000, 'supplier_c' => 24_000_000, 'buyer_d' => 23_700_000]);
        $this->closeNow($competition);
    }

    private function bafoScenario(): void
    {
        $competition = $this->liveWithParticipants('bafo_round', ['supplier_a', 'supplier_c', 'buyer_d'], bafo: true);

        $this->offers($competition, ['supplier_a' => 24_600_000, 'supplier_c' => 24_100_000, 'buyer_d' => 23_900_000]);
        $this->closeNow($competition);

        $owner = $this->owners['issuer'];
        app(StartBafoRound::class)->handle($competition->refresh(), [
            $this->participant($competition, 'supplier_c')->public_id,
            $this->participant($competition, 'buyer_d')->public_id,
        ], 120, $owner, Actor::forUser($owner));
    }

    private function awardedScenario(): void
    {
        $competition = $this->liveWithParticipants('awarded', ['supplier_a', 'supplier_c']);

        // Supplier C's offer meets the reserve (22,000 SAR): an award without justification.
        $this->offers($competition, ['supplier_a' => 22_300_000, 'supplier_c' => 21_800_000]);
        $this->closeNow($competition);

        $owner = $this->owners['issuer'];
        app(IssueAward::class)->handle(
            $competition->refresh(),
            $this->participant($competition, 'supplier_c')->public_id,
            null,
            null,
            false,
            'نبارك لكم الترسية، وسيتواصل معكم فريق المشتريات لاستكمال الإجراءات.',
            'العرض المتصدر ومطابق فنياً.',
            $owner,
            Actor::forUser($owner),
        );
    }

    private function notAwardedScenario(): void
    {
        $competition = $this->publish($this->create('not_awarded', opensAt: null, closesAt: $this->now->addHours(2), auction: true), [
            'buyer_d', 'supplier_a',
        ]);
        $this->join($competition, ['buyer_d']);
        $this->age($competition, minutes: 240);

        $this->offers($competition, ['buyer_d' => self::AUCTION_OPENING]);
        $this->closeNow($competition);

        $owner = $this->owners['issuer'];
        app(CloseWithoutAward::class)->handle(
            $competition,
            CloseReason::query()->where('code', 'not_awarded_prices_above_budget')->firstOrFail(),
            'العرض الوحيد أقل من الحد الأدنى المقبول.',
            Actor::forUser($owner),
        );
    }

    private function cancelledScenario(): void
    {
        $competition = $this->create('cancelled', opensAt: $this->now->addDays(2)->startOfHour(), closesAt: $this->now->addDays(5)->startOfHour());
        $this->invite($competition, ['supplier_a', 'supplier_c']);
        $this->publish($competition);

        app(CancelCompetition::class)->handle(
            $competition,
            CloseReason::query()->where('code', 'cancel_budget_withdrawn')->firstOrFail(),
            null,
            Actor::forUser($this->owners['issuer']),
        );
    }

    private function sponsoredScenario(): void
    {
        $actor = Actor::forUser($this->owners['issuer']);
        $competition = $this->create('sponsored_live', opensAt: null, closesAt: $this->now->addDays(4)->startOfHour());

        app(ConfigureSponsorship::class)->handle($competition->load('organization'), SponsorshipMode::Selected, 2, $actor);

        $this->invite($competition, ['supplier_b'], sponsored: true);
        $this->invite($competition, ['supplier_a']);
        app(GrantSponsoredPass::class)->handle($this->invitationOf($competition, 'supplier_b'), Actor::system());

        $this->publish($competition);
        $this->join($competition, ['supplier_b', 'supplier_a']);
        $this->age($competition, minutes: 180);
    }

    /**
     * A live tender, opened four hours ago and closing in two hours, joined by the given
     * organizations.
     *
     * @param  list<string>  $participants
     */
    private function liveWithParticipants(string $key, array $participants, bool $bafo = false): Competition
    {
        $competition = $this->publish($this->create($key, opensAt: null, closesAt: $this->now->addHours(2), bafo: $bafo), $participants);
        $this->join($competition, $participants);
        $this->age($competition, minutes: 240);

        return $competition;
    }

    private function create(
        string $key,
        ?CarbonImmutable $opensAt,
        CarbonImmutable $closesAt,
        bool $auction = false,
        bool $sealed = false,
        bool $bafo = false,
    ): Competition {
        $preset = match (true) {
            $auction => 'surplus_sale_auction',
            $sealed => 'sealed_rfq',
            default => 'standard_live_tender',
        };

        $rules = match (true) {
            $auction => ['start_price_minor' => self::AUCTION_OPENING, 'result_publication' => 'outcome_and_amount'],
            $sealed => ['start_price_minor' => 18_000_000],
            default => ['start_price_minor' => self::TENDER_CEILING, 'reserve_price_minor' => 22_000_000],
        };

        if ($bafo) {
            $rules['bafo_round'] = ['enabled' => true, 'duration_minutes' => 120];
        }

        $input = new CompetitionInput(
            columns: [
                'title' => self::TITLES[$key],
                'description' => 'نطاق العمل: توريد وتنفيذ وفق كراسة الشروط والمواصفات المرفقة، مع ضمان لمدة سنة من تاريخ الاستلام. الأسعار لا تشمل ضريبة القيمة المضافة.',
                'category_id' => Category::query()->where('code', $auction ? 'surplus_scrap' : 'office_supplies')->value('id')
                    ?? throw new RuntimeException('Catalog reference data is missing (run CatalogReferenceSeeder).'),
                'region_id' => Region::query()->where('code', 'RIY')->value('id'),
                'direction' => $auction ? 'auction' : 'tender',
                'format' => $sealed ? 'sealed' : 'live',
                'bidding_opens_at' => $opensAt,
                'scheduled_close_at' => $closesAt,
                ...RulesMapper::toColumns($rules),
            ],
            fields: ['title', 'description', 'category_id', 'region_id', 'direction', 'format', 'preset_code', 'rules', 'bidding_opens_at', 'scheduled_close_at'],
            presetCode: $preset,
        );

        $owner = $this->owners['issuer'];

        return app(CreateCompetition::class)->handle($this->organizations['issuer'], $input, Actor::forUser($owner));
    }

    /**
     * @param  list<string>  $organizationKeys
     */
    private function invite(Competition $competition, array $organizationKeys, bool $sponsored = false): void
    {
        $rows = array_map(fn (string $key): array => [
            'organization_id' => $this->organizations[$key]->id,
            'name' => $this->owners[$key]->name,
            'sponsored' => $sponsored,
        ], $organizationKeys);

        app(InviteParticipants::class)->handle($competition, $rows, Actor::forUser($this->owners['issuer']));
    }

    /**
     * Publishes the draft, inviting the given organizations first.
     *
     * @param  list<string>  $organizationKeys
     */
    private function publish(Competition $competition, array $organizationKeys = []): Competition
    {
        if ($organizationKeys !== []) {
            $this->invite($competition, $organizationKeys);
        }

        return app(PublishCompetition::class)->handle($competition, Actor::forUser($this->owners['issuer']));
    }

    /**
     * @param  list<string>  $organizationKeys
     */
    private function join(Competition $competition, array $organizationKeys): void
    {
        foreach ($organizationKeys as $key) {
            $owner = $this->owners[$key];
            app(JoinCompetition::class)->handle($this->invitationOf($competition, $key), true, $owner->id, Actor::forUser($owner));
        }
    }

    private function view(Competition $competition, string $organizationKey): void
    {
        app(MarkInvitationViewed::class)->handle($this->invitationOf($competition, $organizationKey), Actor::forUser($this->owners[$organizationKey]));
    }

    /**
     * Uploads a one-page PDF through AddAttachment, as the apps do.
     */
    private function attachDocument(Competition $competition, AttachmentKind $kind, string $fileName, string $title): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bafo-demo-');

        if ($path === false) {
            throw new RuntimeException('Cannot create a temporary file for the demo attachment.');
        }

        file_put_contents($path, self::DEMO_PDF);

        try {
            app(AddAttachment::class)->handle(
                $competition,
                $kind,
                new UploadedFile($path, $fileName, 'application/pdf', null, true),
                null,
                $title,
                Actor::forUser($this->owners['issuer']),
            );
        } finally {
            @unlink($path);
        }
    }

    private function attachLink(Competition $competition, string $url, string $title): void
    {
        app(AddAttachment::class)->handle($competition, AttachmentKind::ExternalLink, null, $url, $title, Actor::forUser($this->owners['issuer']));
    }

    private function comment(Competition $competition, string $organizationKey, string $body, ?Comment $parent = null): Comment
    {
        $user = $this->owners[$organizationKey];
        $actor = Actor::forUser($user);
        $viewer = app(ViewerResolver::class)->for($competition, $actor);

        return app(PostComment::class)->handle($competition, $viewer, $body, $parent, $actor);
    }

    /**
     * One offer per organization, in order, in real time (Bidding's SubmitOffer; the engine
     * allows one offer per participant every 2 seconds).
     *
     * @param  array<string, int>  $amounts
     */
    private function offers(Competition $competition, array $amounts): void
    {
        foreach ($amounts as $key => $amount) {
            $owner = $this->owners[$key];

            app(SubmitOffer::class)->handle(
                $competition->refresh(),
                $this->participant($competition, $key),
                $owner,
                $amount,
                false,
                'demo-'.Str::lower((string) Str::ulid()),
                Actor::forUser($owner),
            );
        }
    }

    /**
     * Brings the close of a live demo competition to now (keeping its derived times coherent),
     * then closes it as the close job would.
     */
    private function closeNow(Competition $competition): void
    {
        $close = app(DbClock::class)->now()->subSecond();
        $competition->refresh();

        $competition->forceFill([
            'scheduled_close_at' => $close,
            'effective_close_at' => $close,
            'hard_stop_at' => $competition->auto_extend_enabled
                ? $close->addSeconds((int) $competition->auto_extend_max * (int) $competition->auto_extend_by_seconds)
                : null,
            'final_window_starts_at' => $competition->final_window_minutes !== null ? $close->subMinutes($competition->final_window_minutes) : null,
            'invitation_cutoff_at' => $competition->invitation_cutoff_at?->min($close),
        ])->save();

        app(CloseDueCompetition::class)->handle($competition->id);
    }

    private function invitationOf(Competition $competition, string $organizationKey): Invitation
    {
        return Invitation::query()
            ->where('competition_id', $competition->id)
            ->where('organization_id', $this->organizations[$organizationKey]->id)
            ->latest('id')
            ->firstOrFail();
    }

    private function participant(Competition $competition, string $organizationKey): Participant
    {
        return Participant::query()
            ->where('competition_id', $competition->id)
            ->where('organization_id', $this->organizations[$organizationKey]->id)
            ->firstOrFail();
    }

    /**
     * Moves the published timeline of a demo competition `$minutes` into the past (publication
     * and opening; with `$includeClose`, the close and its derived times too), so that it is in
     * the wanted phase now. Subscriptions start when Billing seeds them, so the Actions run in
     * real time and the timeline is aged afterwards.
     */
    private function age(Competition $competition, int $minutes, bool $includeClose = false): void
    {
        $competition->refresh();
        $columns = ['published_at', 'bidding_opens_at', 'opened_at'];

        if ($includeClose) {
            array_push($columns, 'scheduled_close_at', 'effective_close_at', 'hard_stop_at', 'final_window_starts_at', 'invitation_cutoff_at');
        }

        foreach ($columns as $column) {
            $value = $competition->getAttribute($column);

            if ($value instanceof CarbonImmutable) {
                $competition->setAttribute($column, $value->subMinutes($minutes));
            }
        }

        $competition->save();
    }

    /**
     * The demo organizations and their owners (IdentityDemoSeeder), found by CR number and e-mail.
     */
    private function loadDemoParties(): bool
    {
        foreach (DemoSeeder::ORGANIZATIONS as $key => $definition) {
            $organization = Organization::query()->where('cr_number', $definition['cr_number'])->first();
            $owner = User::query()->where('email', DemoSeeder::user("{$key}.owner")['email'])->first();

            if ($organization === null || $owner === null) {
                $this->warn("CompetitionsDemoSeeder: demo organization [{$key}] not found (run IdentityDemoSeeder first); skipped.");

                return false;
            }

            $this->organizations[$key] = $organization;
            $this->owners[$key] = $owner;
        }

        return true;
    }

    private function warn(string $message): void
    {
        Log::warning($message);
        $this->command->warn($message);
    }
}
