<?php

declare(strict_types=1);

use App\Modules\Bidding\Models\Offer;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Enums\ExtensionKind;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Events\CompetitionExtended;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionExtension;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Bidding\Scenario;

/*
 * Anti-sniping (ARCHITECTURE §7.7): a change of leader inside the window before the close moves
 * the close to min(max(close, now + by), hard_stop), at most `auto_extend_max` times. Only stage
 * `live` extends; non-competitive offers never prolong the event.
 */

beforeEach(function () {
    Queue::fake();
    Event::fake([CompetitionExtended::class]);
});

/**
 * Window 180 s, extend by 180 s, at most 3 times; hard stop = close + 3 × 180 s.
 *
 * @param  array<string, mixed>  $rules
 */
function snipingScenario(object $test, array $rules = [], ?Closure $state = null): Scenario
{
    return Scenario::make($test, $state ?? fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), 2, [
        'start_price_minor' => 10_000_000,
        'min_step_bps' => 50,
        'min_step_minor' => null,
        'must_beat' => MustBeat::Own,
        'auto_extend_enabled' => true,
        'auto_extend_window_seconds' => 180,
        'auto_extend_by_seconds' => 180,
        'auto_extend_max' => 3,
        ...$rules,
    ]);
}

function closeOf(Scenario $s): CarbonImmutable
{
    return Competition::query()->findOrFail($s->competition->id)->effective_close_at;
}

it('extends the close when the leader changes inside the window', function () {
    $s = snipingScenario($this);
    $close = closeOf($s);

    $this->travelTo($close->subSeconds(60));

    $response = $s->offer(0, 9_900_000)->assertCreated();

    $expected = $close->subSeconds(60)->addSeconds(180); // max(close, now + by)
    $competition = $s->refresh();
    $extension = CompetitionExtension::query()->sole();

    expect($competition->effective_close_at->equalTo($expected))->toBeTrue()
        ->and($competition->extension_count)->toBe(1)
        ->and($extension->kind)->toBe(ExtensionKind::Auto)
        ->and($extension->previous_close_at->equalTo($close))->toBeTrue()
        ->and($extension->new_close_at->equalTo($expected))->toBeTrue()
        ->and($extension->triggered_by_offer_id)->toBe(Offer::query()->sole()->id)
        ->and($response->json('data.live.extension_count'))->toBe(1)
        ->and($response->json('data.live.effective_close_at'))->toBe($expected->utc()->format('Y-m-d\TH:i:s.v\Z'));

    Event::assertDispatched(CompetitionExtended::class, fn (CompetitionExtended $e) => $e->extension->kind === ExtensionKind::Auto);
});

it('keeps accepting offers until the extended close', function () {
    $s = snipingScenario($this);
    $close = closeOf($s);

    $this->travelTo($close->subSeconds(10));
    $s->offer(0, 9_900_000)->assertCreated();

    // After the original close, the extended close still accepts a new leader.
    $this->travelTo($close->addSeconds(30));
    $s->offer(1, 9_800_000)->assertCreated();

    expect($s->refresh()->extension_count)->toBe(2);
});

it('does not extend outside the window', function () {
    $s = snipingScenario($this);
    $close = closeOf($s);

    $this->travelTo($close->subSeconds(181));
    $s->offer(0, 9_900_000)->assertCreated();

    expect(closeOf($s)->equalTo($close))->toBeTrue()
        ->and(CompetitionExtension::query()->count())->toBe(0);

    Event::assertNotDispatched(CompetitionExtended::class);
});

it('extends only on a change of leader, never on a non-competitive offer', function () {
    $s = snipingScenario($this);
    $close = closeOf($s);

    $this->travelTo($close->subMinutes(30));
    $s->offer(0, 9_500_000)->assertCreated();

    $this->travelTo($close->subSeconds(60));
    $s->offer(1, 9_900_000)->assertCreated(); // worse than the leader: no change of leader
    $s->offer(0, 9_400_000)->assertCreated(); // the leader improves: still the same leader

    expect(closeOf($s)->equalTo($close))->toBeTrue()->and($s->refresh()->extension_count)->toBe(0);
});

it('stops extending after auto_extend_max extensions', function () {
    $s = snipingScenario($this, ['extension_count' => 3]);
    $close = closeOf($s);

    $this->travelTo($close->subSeconds(60));
    $s->offer(0, 9_900_000)->assertCreated();

    expect(closeOf($s)->equalTo($close))->toBeTrue();
});

it('never moves the close past the hard stop', function () {
    $s = snipingScenario($this);
    $close = closeOf($s);
    // Through the model: its date format keeps the microseconds of the close.
    $s->refresh()->forceFill(['hard_stop_at' => $close->addSeconds(60)])->save();

    $this->travelTo($close->subSeconds(10));
    $s->offer(0, 9_900_000)->assertCreated();

    expect(closeOf($s)->equalTo($close->addSeconds(60)))->toBeTrue();

    // At the hard stop there is nothing left to extend.
    $this->travelTo($close->addSeconds(50));
    $s->offer(1, 9_800_000)->assertCreated();

    expect(closeOf($s)->equalTo($close->addSeconds(60)))->toBeTrue()
        ->and($s->refresh()->extension_count)->toBe(1);
});

it('does not stack extensions: the close becomes max(close, now + by)', function () {
    $s = snipingScenario($this);
    $close = closeOf($s);

    $this->travelTo($close->subSeconds(100));
    $s->offer(0, 9_900_000)->assertCreated();
    expect(closeOf($s)->equalTo($close->addSeconds(80)))->toBeTrue();

    $this->travelTo($close->subSeconds(90));
    $s->offer(1, 9_800_000)->assertCreated();

    // now + by = close + 90: only 10 s later, not another 180 s.
    expect(closeOf($s)->equalTo($close->addSeconds(90)))->toBeTrue()
        ->and($s->refresh()->extension_count)->toBe(2);
});

it('does not extend in the initial phase', function () {
    // The final window starts 60 minutes before the close; a huge window reaches back into the initial phase.
    $s = snipingScenario($this, ['auto_extend_window_seconds' => 7200], fn (CompetitionFactory $f) => $f->live());
    $close = closeOf($s);

    $s->offer(0, 9_900_000)->assertCreated()->assertJsonPath('data.offer.stage', 'initial');

    expect(closeOf($s)->equalTo($close))->toBeTrue();
});

it('does not extend when auto-extend is off', function () {
    $s = snipingScenario($this, ['auto_extend_enabled' => false, 'auto_extend_window_seconds' => null, 'auto_extend_by_seconds' => null, 'auto_extend_max' => null, 'hard_stop_at' => null]);
    $close = closeOf($s);

    $this->travelTo($close->subSeconds(5));
    $s->offer(0, 9_900_000)->assertCreated();

    expect(closeOf($s)->equalTo($close))->toBeTrue();
});
