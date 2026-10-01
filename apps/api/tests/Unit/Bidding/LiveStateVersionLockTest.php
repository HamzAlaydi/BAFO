<?php

declare(strict_types=1);

use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Bidding\Services\LiveStateManager;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Models\Competition;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\Support\Bidding\Scenario;
use Tests\TestCase;

/*
 * The live-state `version` must move by exactly one per audience-visible change (§9.3): clients
 * drop any snapshot whose `v` is not newer than the last one applied.
 *
 * Engine writers (offers, voids, BAFO, award) read the row, `version++` and save it under the
 * competition lock; lifecycle listeners call the atomic `bumpVersion()` without that lock. Found
 * while chasing lost live updates in the web e2e: a bump committing between the writer's read and
 * save was overwritten, and two different snapshots went out with the same `v`. `forLocked()` now
 * locks the row, so a bump waits for the writer's commit.
 *
 * The bump runs on a second connection and needs committed rows, so this test commits (database
 * truncation) instead of running inside the RefreshDatabase transaction, and cleans up after itself.
 */

uses(TestCase::class, DatabaseTruncation::class);

afterEach(function () {
    // A failed expectation may leave the writer's transaction open, holding its locks.
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    DB::purge('live_state_bumper');
    config(['database.connections.live_state_bumper' => null]);
    $this->truncateTablesForAllConnections();
});

/** Runs `bumpVersion()` on its own connection, giving up after 300 ms of waiting for a lock. */
function bumpOnOtherConnection(Competition $competition): int
{
    config(['database.connections.live_state_bumper' => config('database.connections.'.config('database.default'))]);
    $other = DB::connection('live_state_bumper');
    $other->statement("set lock_timeout = '300ms'");
    $default = DB::getDefaultConnection();
    DB::setDefaultConnection('live_state_bumper');

    try {
        return app(LiveStateManager::class)->bumpVersion($competition->id);
    } finally {
        DB::setDefaultConnection($default);
    }
}

it('makes a lifecycle bump wait while an engine writer holds the live state', function () {
    $s = Scenario::make($this, fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), 1);
    $manager = app(LiveStateManager::class);
    expect($manager->bumpVersion($s->competition->id))->toBe(1);

    DB::beginTransaction();
    $locked = Competition::query()->whereKey($s->competition->id)->lockForUpdate()->firstOrFail();
    $state = $manager->forLocked($locked);
    expect($state->version)->toBe(1);

    // The writer is between its read and its save: a concurrent bump must not get through.
    expect(fn () => bumpOnOtherConnection($s->competition))->toThrow(QueryException::class, 'lock timeout');

    $state->version++;
    $state->save();
    DB::commit();

    // After the writer's commit the bump goes through on top of it: 1 → 2 (writer) → 3 (bump).
    expect(bumpOnOtherConnection($s->competition))->toBe(3)
        ->and(CompetitionLiveState::query()->findOrFail($s->competition->id)->version)->toBe(3);
});

it('creates the missing row and returns it locked with its defaults', function () {
    $s = Scenario::make($this, fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), 1);

    DB::beginTransaction();
    $locked = Competition::query()->whereKey($s->competition->id)->lockForUpdate()->firstOrFail();
    $state = app(LiveStateManager::class)->forLocked($locked);

    expect($state->exists)->toBeTrue()
        ->and($state->version)->toBe(0)
        ->and($state->last_seq)->toBe(0)
        ->and($state->accepted_offer_count)->toBe(0);

    DB::commit();
});
