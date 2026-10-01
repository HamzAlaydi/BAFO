<?php

declare(strict_types=1);

use App\Modules\Bidding\Enums\OfferStage;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\OfferVoid;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 * The append-only, hash-chained offer ledger (ARCHITECTURE §5.6). Each rejected write runs in
 * its own savepoint (DB::transaction inside the test transaction) so the test can go on.
 */

it('rejects UPDATE on the offers ledger', function () {
    $offer = Offer::factory()->create();

    expect(fn () => DB::transaction(fn () => DB::table('offers')->where('id', $offer->id)->update(['amount_minor' => 1])))
        ->toThrow(QueryException::class, 'table offers is append-only');

    $offer->amount_minor = 100;

    expect(fn () => DB::transaction(fn () => $offer->save()))
        ->toThrow(QueryException::class, 'append-only')
        ->and(Offer::query()->whereKey($offer->id)->value('amount_minor'))->toBe($offer->getOriginal('amount_minor'));
});

it('rejects DELETE on the offers ledger', function () {
    $offer = Offer::factory()->create();

    expect(fn () => DB::transaction(fn () => DB::table('offers')->where('id', $offer->id)->delete()))
        ->toThrow(QueryException::class, 'table offers is append-only')
        ->and(fn () => DB::transaction(fn () => $offer->delete()))
        ->toThrow(QueryException::class, 'append-only')
        ->and(Offer::query()->whereKey($offer->id)->exists())->toBeTrue();
});

it('rejects UPDATE and DELETE on offer voids', function () {
    $void = OfferVoid::factory()->create();

    expect(fn () => DB::transaction(fn () => DB::table('offer_voids')->where('id', $void->id)->update(['note' => 'x'])))
        ->toThrow(QueryException::class, 'table offer_voids is append-only')
        ->and(fn () => DB::transaction(fn () => DB::table('offer_voids')->where('id', $void->id)->delete()))
        ->toThrow(QueryException::class, 'table offer_voids is append-only');
});

it('chains the ledger rows with the contract hash, microseconds included', function () {
    $participant = Participant::factory()->create();
    $other = Participant::factory()->create(['competition_id' => $participant->competition_id]);

    Offer::factory()->for($participant)->amount(9_000_000)->create();
    Offer::factory()->for($other)->amount(8_990_000)->create();
    Offer::factory()->count(2)->for($participant)->create();

    $competition = Competition::query()->findOrFail($participant->competition_id);
    $offers = Offer::query()->where('competition_id', $competition->id)->orderBy('seq')->get();
    $publicIds = Participant::query()->whereIn('id', [$participant->id, $other->id])->pluck('public_id', 'id');

    expect($offers->pluck('seq')->all())->toBe([1, 2, 3, 4])
        ->and($offers->first()?->prev_hash)->toBeNull();

    $previous = null;

    foreach ($offers as $offer) {
        expect($offer->prev_hash)->toBe($previous?->hash)
            ->and($offer->rank_key)->toBe($offer->amount_minor) // tender: rank_key = amount
            ->and($offer->created_at?->equalTo($offer->accepted_at))->toBeTrue()
            ->and($offer->hash)->toBe(Offer::hashFor(
                $previous?->hash,
                $competition->public_id,
                $offer->seq,
                (string) $publicIds[$offer->participant_id],
                $offer->amount_minor,
                $offer->stage,
                $offer->accepted_at, // read back from the tstz6 column
            ));

        $previous = $offer;
    }
});

it('stores the auction rank key as the negated amount', function () {
    $participant = Participant::factory()->create([
        'competition_id' => Competition::factory()->auction()->live(),
    ]);

    $offer = Offer::factory()->for($participant)->amount(5_000_000)->create();

    expect($offer->rank_key)->toBe(-5_000_000)
        ->and($offer->stage)->toBe(OfferStage::Live);
});

it('enforces the ledger constraints', function () {
    $offer = Offer::factory()->create();

    // CHECK amount_minor > 0
    expect(fn () => DB::transaction(fn () => Offer::factory()->for(Participant::query()->findOrFail($offer->participant_id))->amount(0)->create()))
        ->toThrow(QueryException::class, 'offers_amount_minor_positive')
        // UNIQUE (competition_id, seq)
        ->and(fn () => DB::transaction(fn () => Offer::factory()
            ->for(Participant::query()->findOrFail($offer->participant_id))
            ->create(['seq' => $offer->seq])))
        ->toThrow(QueryException::class, 'offers_competition_id_seq_unique')
        // UNIQUE (participant_id, idempotency_key)
        ->and(fn () => DB::transaction(fn () => Offer::factory()
            ->for(Participant::query()->findOrFail($offer->participant_id))
            ->create(['idempotency_key' => $offer->idempotency_key])))
        ->toThrow(QueryException::class, 'offers_participant_id_idempotency_key_unique');
});
