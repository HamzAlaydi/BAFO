<?php

declare(strict_types=1);

use App\Modules\Bidding\Broadcasting\IssuerLiveUpdated;
use App\Modules\Bidding\Broadcasting\ParticipantLiveUpdated;
use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Competitions\Actions\OpenCompetition;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\Bidding\Scenario;

/*
 * Queued listeners of domain events run after the producing transaction commits (ARCHITECTURE §10).
 *
 * For a queued listener Laravel ignores `ShouldHandleEventsAfterCommit`: only `ShouldQueueAfterCommit`
 * (or `$afterCommit`) holds the job until the commit. Found by the live web e2e: the tick's
 * `OpenCompetition` queued `BumpVersionAndBroadcast` inside its transaction, the worker ran it before
 * the commit, read the competition as still `scheduled`, bumped the version and broadcast that stale
 * snapshot, so the rooms never saw the competition open (the real update had the same `v`).
 */

/** @return list<class-string> */
function moduleListenerClasses(): array
{
    $classes = [];

    foreach (glob(app_path('Modules/*/Listeners/*.php')) ?: [] as $file) {
        $module = basename(dirname($file, 2));
        $class = 'App\\Modules\\'.$module.'\\Listeners\\'.basename($file, '.php');

        if (class_exists($class) && ! (new ReflectionClass($class))->isAbstract()) {
            $classes[] = $class;
        }
    }

    return $classes;
}

it('holds every queued after-commit listener on the queue until the commit', function () {
    $offenders = collect(moduleListenerClasses())
        ->filter(fn (string $class): bool => is_subclass_of($class, ShouldQueue::class)
            && is_subclass_of($class, ShouldHandleEventsAfterCommit::class)
            && ! is_subclass_of($class, ShouldQueueAfterCommit::class))
        ->values()
        ->all();

    expect(moduleListenerClasses())->not->toBeEmpty()
        ->and($offenders)->toBe([]);
});

it('broadcasts the opening only after the opening commits, with the committed status', function () {
    Event::fake([IssuerLiveUpdated::class, ParticipantLiveUpdated::class]);
    $s = Scenario::make($this, fn (CompetitionFactory $f) => $f->withoutFinalWindow()->scheduled(), bidders: 2);
    $s->competition->forceFill(['bidding_opens_at' => CarbonImmutable::now()->subSecond()])->save();

    DB::transaction(function () use ($s): void {
        expect(app(OpenCompetition::class)->handle($s->competition->id))->toBeTrue();

        // Still inside the producing transaction: nothing may have run yet.
        expect(CompetitionLiveState::query()->find($s->competition->id))->toBeNull();
        Event::assertNotDispatched(IssuerLiveUpdated::class);
    });

    expect(CompetitionLiveState::query()->findOrFail($s->competition->id)->version)->toBe(1);
    Event::assertDispatched(IssuerLiveUpdated::class, fn (IssuerLiveUpdated $e): bool => $e->snapshot['status'] === 'live' && $e->snapshot['v'] === 1);
    Event::assertDispatched(ParticipantLiveUpdated::class, fn (ParticipantLiveUpdated $e): bool => $e->snapshot['status'] === 'live' && $e->snapshot['v'] === 1);
    Event::assertDispatchedTimes(ParticipantLiveUpdated::class, 2);
});
