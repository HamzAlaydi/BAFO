<?php

declare(strict_types=1);

use App\Support\Clock\CarbonDbClock;
use App\Support\Clock\DbClock;
use App\Support\Clock\PostgresDbClock;
use App\Support\Database\Concerns\UsesPreciseTimestamps;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PreciseProbe extends Model
{
    use UsesPreciseTimestamps;

    protected $table = 'precise_probes';

    protected $fillable = ['happened_at'];

    protected function casts(): array
    {
        return ['happened_at' => 'immutable_datetime'];
    }
}

it('binds the travel-aware clock in tests', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 12:00:00.250', 'UTC'));

    expect(app(DbClock::class))->toBeInstanceOf(CarbonDbClock::class)
        ->and(app(DbClock::class)->now()->format('Y-m-d H:i:s.v e'))->toBe('2026-10-01 12:00:00.250 UTC');
});

it('reads clock_timestamp() from PostgreSQL in UTC with microseconds', function () {
    $clock = new PostgresDbClock(app('db'));

    $before = CarbonImmutable::now()->subSeconds(5);
    $first = $clock->now();
    $second = $clock->now();

    expect($first->getTimezone()->getName())->toBe('UTC')
        ->and($first->greaterThan($before))->toBeTrue()
        // clock_timestamp() advances inside the (test) transaction, unlike now().
        ->and($second->greaterThan($first))->toBeTrue();
});

it('is the production binding', function () {
    $this->app->forgetInstance(DbClock::class);
    $this->app->singleton(DbClock::class, PostgresDbClock::class);

    expect(app(DbClock::class))->toBeInstanceOf(PostgresDbClock::class);
});

it('keeps microseconds on precise timestamp columns', function () {
    Schema::create('precise_probes', function (Blueprint $table) {
        $table->id();
        $table->timestampTz('happened_at', 6);
        $table->timestampsTz(6);
    });

    $this->travelTo(CarbonImmutable::parse('2026-10-01 12:59:58.412345', 'UTC'));
    $probe = PreciseProbe::query()->create(['happened_at' => CarbonImmutable::parse('2026-10-01 12:59:59.000001', 'UTC')]);
    $fresh = PreciseProbe::query()->findOrFail($probe->id);

    expect($fresh->happened_at->format('Y-m-d H:i:s.u'))->toBe('2026-10-01 12:59:59.000001')
        ->and($fresh->created_at?->format('H:i:s.u'))->toBe('12:59:58.412345')
        ->and(DB::table('precise_probes')->value('happened_at'))->toStartWith('2026-10-01 12:59:59.000001');

    // Values stored without a fraction still parse.
    DB::table('precise_probes')->update(['happened_at' => '2026-10-01 13:00:00+00']);
    expect(PreciseProbe::query()->findOrFail($probe->id)->happened_at->format('H:i:s.u'))->toBe('13:00:00.000000');
});
