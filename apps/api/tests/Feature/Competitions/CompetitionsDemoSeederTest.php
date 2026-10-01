<?php

declare(strict_types=1);

use App\Modules\Billing\Database\Seeders\BillingDemoSeeder;
use App\Modules\Billing\Enums\EntitlementSource;
use App\Modules\Competitions\Database\Seeders\CompetitionsDemoSeeder;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\Direction;
use App\Modules\Competitions\Enums\Phase;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Database\Seeders\IdentityDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

it('seeds Issuer Co competitions in every status and both directions', function (): void {
    Mail::fake();
    Storage::fake('private');

    $this->seed([DatabaseSeeder::class, IdentityDemoSeeder::class, BillingDemoSeeder::class, CompetitionsDemoSeeder::class]);

    $expected = [
        'draft' => CompetitionStatus::Draft,
        'scheduled' => CompetitionStatus::Scheduled,
        'live_initial' => CompetitionStatus::Live,
        'live_final_window' => CompetitionStatus::Live,
        'live_auction' => CompetitionStatus::Live,
        'live_sealed' => CompetitionStatus::Live,
        'closed' => CompetitionStatus::Closed,
        'bafo_round' => CompetitionStatus::BafoRound,
        'awarded' => CompetitionStatus::Awarded,
        'not_awarded' => CompetitionStatus::NotAwarded,
        'cancelled' => CompetitionStatus::Cancelled,
        'sponsored_live' => CompetitionStatus::Live,
    ];

    foreach ($expected as $key => $status) {
        expect(CompetitionsDemoSeeder::find($key)?->status)->toBe($status, "scenario {$key}");
    }

    expect(CompetitionsDemoSeeder::find('live_initial')?->phaseAt(now()))->toBe(Phase::Initial)
        ->and(CompetitionsDemoSeeder::find('live_final_window')?->phaseAt(now()))->toBe(Phase::FinalWindow)
        ->and(CompetitionsDemoSeeder::find('live_auction')?->direction)->toBe(Direction::Auction)
        ->and(Competition::query()->where('direction', Direction::Tender->value)->exists())->toBeTrue();

    $sponsored = CompetitionsDemoSeeder::find('sponsored_live');

    expect(Participant::query()->where('competition_id', $sponsored?->id)->where('entitlement_source', EntitlementSource::SponsoredPass->value)->count())
        ->toBe(1);
});
