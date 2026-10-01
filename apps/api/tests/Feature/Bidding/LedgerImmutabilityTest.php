<?php

declare(strict_types=1);

use App\Modules\Admin\Models\Admin;
use App\Modules\Bidding\Actions\VoidOffer;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\OfferVoid;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use App\Support\Auth\Actor;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Bidding\Scenario;

/*
 * The offer ledger is append-only (ARCHITECTURE §5.6): the database rejects UPDATE and DELETE on
 * `offers` and `offer_voids`, whatever the path, and the engine never needs either.
 */

function ledgerWrite(Closure $write): ?string
{
    try {
        DB::transaction($write);
    } catch (QueryException $exception) {
        return $exception->getMessage();
    }

    return null;
}

it('rejects updates and deletes of accepted offers', function () {
    $s = Scenario::make($this, attributes: ['start_price_minor' => 10_000_000]);
    $s->offer(0, 9_600_000)->assertCreated();
    $offer = Offer::query()->sole();

    expect(ledgerWrite(fn () => Offer::query()->whereKey($offer->id)->update(['amount_minor' => 1])))->toContain('append-only')
        ->and(ledgerWrite(fn () => DB::table('offers')->where('id', $offer->id)->delete()))->toContain('append-only')
        ->and(ledgerWrite(fn () => $offer->delete()))->toContain('append-only')
        ->and(ledgerWrite(fn () => DB::statement('update offers set hash = ? where id = ?', [str_repeat('0', 64), $offer->id])))->toContain('append-only')
        ->and(Offer::query()->sole()->amount_minor)->toBe(9_600_000);
});

it('rejects updates and deletes of voids', function () {
    $s = Scenario::make($this, attributes: ['start_price_minor' => 10_000_000]);
    $s->offer(0, 9_600_000)->assertCreated();

    $void = app(VoidOffer::class)->handle(
        Offer::query()->sole(),
        CloseReason::factory()->kind(CloseReasonKind::VoidOffer)->create(),
        null,
        Actor::forAdmin(Admin::factory()->create()),
    );

    expect(ledgerWrite(fn () => OfferVoid::query()->whereKey($void->id)->update(['note' => 'changed'])))->toContain('append-only')
        ->and(ledgerWrite(fn () => OfferVoid::query()->whereKey($void->id)->delete()))->toContain('append-only')
        ->and(OfferVoid::query()->count())->toBe(1);
});

it('keeps the chain verifiable from the stored rows alone', function () {
    $s = Scenario::make($this, attributes: ['start_price_minor' => 10_000_000], bidders: 3);

    foreach ([[0, 9_900_000], [1, 9_800_000], [2, 9_700_000], [0, 9_600_000]] as [$index, $amount]) {
        $s->offer($index, $amount)->assertCreated();
    }

    $offers = Offer::query()->with(['participant', 'competition'])->orderBy('seq')->get();
    $head = null;

    foreach ($offers as $offer) {
        expect($offer->prev_hash)->toBe($head)
            ->and($offer->hash)->toBe(Offer::hashFor($head, $offer->competition->public_id, $offer->seq, $offer->participant->public_id, $offer->amount_minor, $offer->stage, $offer->accepted_at));

        $head = $offer->hash;
    }

    // Tampering with any column breaks the recomputed hash.
    $first = $offers->first();
    expect(Offer::hashFor(null, $first->competition->public_id, 1, $first->participant->public_id, $first->amount_minor + 100, $first->stage, $first->accepted_at))
        ->not->toBe($first->hash);
});
