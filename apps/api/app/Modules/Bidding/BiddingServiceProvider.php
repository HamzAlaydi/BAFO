<?php

declare(strict_types=1);

namespace App\Modules\Bidding;

use App\Modules\Bidding\Broadcasting\Channels\BiddingChannels;
use App\Modules\Bidding\Console\Commands\BiddingTickCommand;
use App\Modules\Bidding\Contracts\BiddingEngine;
use App\Modules\Bidding\Events\AwardIssued;
use App\Modules\Bidding\Events\AwardRevoked;
use App\Modules\Bidding\Events\BafoRoundEnded;
use App\Modules\Bidding\Events\BafoRoundStarted;
use App\Modules\Bidding\Events\OfferAccepted;
use App\Modules\Bidding\Events\OfferVoided;
use App\Modules\Bidding\Listeners\BroadcastLiveChange;
use App\Modules\Bidding\Listeners\BroadcastOfferAccepted;
use App\Modules\Bidding\Listeners\BumpVersionAndBroadcast;
use App\Modules\Bidding\Listeners\EndCancelledBafoRound;
use App\Modules\Bidding\Listeners\QueueCompetitionReport;
use App\Modules\Bidding\Policies\BiddingPolicy;
use App\Modules\Bidding\Services\BiddingEngineService;
use App\Modules\Competitions\Events\CompetitionCancelled;
use App\Modules\Competitions\Events\CompetitionClosed;
use App\Modules\Competitions\Events\CompetitionClosedWithoutAward;
use App\Modules\Competitions\Events\CompetitionExtended;
use App\Modules\Competitions\Events\CompetitionFinalWindowStarted;
use App\Modules\Competitions\Events\CompetitionStatusChanged;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Models\Membership;
use App\Support\Files\File;
use App\Support\Files\FileAccessRegistry;
use App\Support\Files\FilePurpose;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Settings\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Bidding module.
 *
 * The tender/auction engine (R5): the append-only offer ledger, ranking, the
 * VisibilityProjector, anti-sniping, sealed unlock, the BAFO round, award and revoke,
 * admin void, live snapshots and heartbeat, the private realtime channels, and the
 * result PDF.
 *
 * Contract bound here (ARCHITECTURE §3.6): BiddingEngine.
 *
 * HTTP routes: routes/app_v1/bidding.php and routes/public_v1/bidding.php.
 * Conventions (config, migrations, views, web routes): App\Support\Modules\ModuleServiceProvider.
 */
final class BiddingServiceProvider extends ModuleServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $policies = [];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(BiddingEngine::class, BiddingEngineService::class);
    }

    public function boot(): void
    {
        parent::boot();

        /** @var array<string, mixed> $defaults */
        $defaults = config('bafo.bidding.settings', []);
        $this->app->make(Settings::class)->defaults($defaults);

        $this->registerRateLimiter();
        $this->registerAbilities();
        $this->registerChannels();
        $this->registerListeners();
        $this->registerFileAccess();

        if ($this->app->runningInConsole()) {
            $this->commands([BiddingTickCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('bidding:tick')->everyTenSeconds()->withoutOverlapping()->onOneServer();
        });
    }

    /**
     * `offers`: 60 per minute per user (ARCHITECTURE §2.2 item 7). The engine adds 1 per 2 s
     * per participant (§7.4 step 6).
     */
    private function registerRateLimiter(): void
    {
        RateLimiter::for('offers', static fn (Request $request): Limit => Limit::perMinute((int) config('bafo.bidding.offers_per_minute', 60))
            ->by('offers:'.($request->user()?->getAuthIdentifier() ?? 'ip:'.$request->ip())));
    }

    private function registerAbilities(): void
    {
        Gate::define('bidding.submit-offers', [BiddingPolicy::class, 'submitOffers']);
        Gate::define('bidding.award', [BiddingPolicy::class, 'award']);
    }

    /**
     * ARCHITECTURE §9.2: the issuer channel and one channel per participant organization.
     */
    private function registerChannels(): void
    {
        BiddingChannels::register();
    }

    /**
     * ARCHITECTURE §10: every Bidding listener is queued after commit.
     */
    private function registerListeners(): void
    {
        Event::listen(OfferAccepted::class, BroadcastOfferAccepted::class);

        foreach ([BafoRoundStarted::class, BafoRoundEnded::class, AwardIssued::class, AwardRevoked::class, OfferVoided::class] as $event) {
            Event::listen($event, BroadcastLiveChange::class);
        }

        foreach ([CompetitionStatusChanged::class, CompetitionExtended::class, CompetitionFinalWindowStarted::class] as $event) {
            Event::listen($event, BumpVersionAndBroadcast::class);
        }

        foreach ([CompetitionClosed::class, CompetitionClosedWithoutAward::class, AwardIssued::class, AwardRevoked::class] as $event) {
            Event::listen($event, QueueCompetitionReport::class);
        }

        Event::listen(CompetitionCancelled::class, EndCancelledBafoRound::class);
    }

    /**
     * ARCHITECTURE §8.5: the result report is readable by the issuer organization's members.
     */
    private function registerFileAccess(): void
    {
        $this->app->make(FileAccessRegistry::class)->register(
            FilePurpose::CompetitionReport,
            static function (Authenticatable $user, File $file): bool {
                $organizationId = Membership::query()
                    ->where('user_id', $user->getAuthIdentifier())
                    ->where('status', MembershipStatus::Active->value)
                    ->value('organization_id');

                return $organizationId !== null
                    && (int) $organizationId === $file->organization_id
                    && Competition::query()->where('organization_id', $file->organization_id)->exists();
            },
        );
    }
}
