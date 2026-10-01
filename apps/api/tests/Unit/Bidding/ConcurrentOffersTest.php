<?php

declare(strict_types=1);

use App\Modules\Bidding\Actions\SubmitOffer;
use App\Modules\Bidding\Events\OfferAccepted;
use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\User;
use App\Support\Auth\Actor;
use App\Support\Clock\DbClock;
use App\Support\Clock\PostgresDbClock;
use App\Support\Exceptions\ApiException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\Bidding\Scenario;
use Tests\TestCase;

/*
 * The serialisation point of ARCHITECTURE §7.4 (D10): two bids submitted at the same moment by
 * two processes are processed one after the other under `SELECT … FOR UPDATE` on the
 * competition row, with the DB clock read after the lock. The ledger stays gapless and chained
 * and the ranking reflects both offers whatever order the lock grants.
 *
 * The bids must see committed data from their own connections, so this test commits (database
 * truncation) instead of running inside the RefreshDatabase transaction, and cleans up after
 * itself.
 */

uses(TestCase::class, DatabaseTruncation::class);

afterEach(function () {
    $this->truncateTablesForAllConnections();
});

/**
 * Forks a process that submits one offer on its own database connection. The child reports
 * through a file and ends with SIGKILL, so it never runs the parent's destructors (which would
 * close the parent's inherited database socket).
 */
function forkBid(Competition $competition, Participant $participant, User $user, int $amount, string $resultFile): int
{
    $pid = pcntl_fork();

    if ($pid !== 0) {
        return $pid;
    }

    // Child: keep the inherited PDO alive (never closed from here), then open a fresh connection.
    $GLOBALS['__bidding_inherited_pdo'] = DB::connection()->getPdo();
    DB::purge();
    DB::reconnect();

    try {
        $result = app(SubmitOffer::class)->handle(
            $competition,
            $participant,
            $user,
            $amount,
            false,
            'concurrent-'.$participant->id,
            Actor::forUser($user),
        );
        file_put_contents($resultFile, json_encode(['ok' => true, 'seq' => $result->offer->seq]));
    } catch (ApiException $exception) {
        file_put_contents($resultFile, json_encode(['ok' => false, 'code' => $exception->errorCode]));
    } catch (Throwable $exception) {
        file_put_contents($resultFile, json_encode(['ok' => false, 'code' => $exception::class.': '.$exception->getMessage()]));
    }

    posix_kill(posix_getpid(), SIGKILL);

    exit(1); // not reached
}

it('processes two near-simultaneous bids serially and ranks them correctly', function () {
    // The real database clock: clock_timestamp() read after the row lock.
    $this->app->instance(DbClock::class, $this->app->make(PostgresDbClock::class));
    Event::fake([OfferAccepted::class]);

    $s = Scenario::make($this, fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), 2, [
        'start_price_minor' => 10_000_000,
        'min_step_bps' => 50,
        'min_step_minor' => null,
        'must_beat' => MustBeat::Own,
        'auto_extend_enabled' => false,
        'auto_extend_window_seconds' => null,
        'auto_extend_by_seconds' => null,
        'auto_extend_max' => null,
        'hard_stop_at' => null,
    ]);

    // A third connection holds the competition row lock, so both bids queue up behind it.
    config(['database.connections.bidding_locker' => config('database.connections.'.config('database.default'))]);
    $locker = DB::connection('bidding_locker');
    $locker->beginTransaction();
    $locker->select('select id from competitions where id = ? for update', [$s->competition->id]);

    $directory = sys_get_temp_dir().'/bafo-bidding-'.getmypid().'-'.bin2hex(random_bytes(4));
    mkdir($directory);

    $pids = [
        forkBid($s->competition, $s->participant(0), $s->bidder(0), 9_600_000, "{$directory}/0.json"),
        forkBid($s->competition, $s->participant(1), $s->bidder(1), 9_500_000, "{$directory}/1.json"),
    ];

    // Wait until both bids are blocked on the row lock (up to 10 s).
    $waiting = 0;

    for ($i = 0; $i < 200 && $waiting < 2; $i++) {
        usleep(50_000);
        $waiting = (int) $locker->scalar(
            "select count(*) from pg_stat_activity where datname = current_database() and wait_event_type = 'Lock' and pid <> pg_backend_pid()",
        );
    }

    expect($waiting)->toBe(2, 'both bids must be waiting on SELECT … FOR UPDATE');

    // Release the lock: the two bids now run one after the other.
    $locker->commit();

    $deadline = microtime(true) + 20;

    foreach ($pids as $pid) {
        while (pcntl_waitpid($pid, $status, WNOHANG) === 0) {
            if (microtime(true) > $deadline) {
                array_map(static fn (int $p) => posix_kill($p, SIGKILL), $pids);

                throw new RuntimeException('A bid did not finish in time.');
            }

            usleep(20_000);
        }
    }

    $results = [
        json_decode((string) file_get_contents("{$directory}/0.json"), true),
        json_decode((string) file_get_contents("{$directory}/1.json"), true),
    ];
    array_map('unlink', glob("{$directory}/*.json") ?: []);
    rmdir($directory);
    DB::purge('bidding_locker');

    expect($results[0]['ok'] ?? null)->toBeTrue(json_encode($results[0]))
        ->and($results[1]['ok'] ?? null)->toBeTrue(json_encode($results[1]))
        ->and([$results[0]['seq'], $results[1]['seq']])->toEqualCanonicalizing([1, 2]);

    $offers = Offer::query()->where('competition_id', $s->competition->id)->with('participant')->orderBy('seq')->get();

    expect($offers)->toHaveCount(2)
        ->and($offers[0]->prev_hash)->toBeNull()
        ->and($offers[1]->prev_hash)->toBe($offers[0]->hash)
        ->and($offers[1]->accepted_at->greaterThan($offers[0]->accepted_at))->toBeTrue();

    foreach ($offers as $offer) {
        expect($offer->hash)->toBe(Offer::hashFor(
            $offer->prev_hash, $s->competition->public_id, $offer->seq, $offer->participant->public_id,
            $offer->amount_minor, $offer->stage, $offer->accepted_at,
        ));
    }

    $state = CompetitionLiveState::query()->findOrFail($s->competition->id);

    expect($state->last_seq)->toBe(2)
        ->and($state->version)->toBe(2)
        ->and($state->accepted_offer_count)->toBe(2)
        ->and($state->participants_with_offers)->toBe(2)
        ->and($state->ledger_head_hash)->toBe($offers[1]->hash)
        // The lower tender offer leads, whichever bid got the lock first.
        ->and($state->leader_participant_id)->toBe($s->participant(1)->id)
        ->and(ParticipantStanding::query()->findOrFail($s->participant(1)->id)->rank)->toBe(1)
        ->and(ParticipantStanding::query()->findOrFail($s->participant(0)->id)->rank)->toBe(2);
})->skip(! function_exists('pcntl_fork') || ! function_exists('posix_kill'), 'needs the pcntl and posix extensions');
