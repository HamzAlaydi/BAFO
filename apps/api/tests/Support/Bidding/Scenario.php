<?php

declare(strict_types=1);

namespace Tests\Support\Bidding;

use App\Modules\Bidding\Models\Offer;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A competition with an issuer (owner user) and N joined participants, each with the member
 * user that joined (who holds `participation.submit_offers`).
 *
 *     $s = Scenario::make(fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), bidders: 3);
 *     $s->offer(0, 9_900_000)->assertCreated();
 */
final class Scenario
{
    /**
     * @param  list<Participant>  $participants
     * @param  list<User>  $bidders
     */
    private function __construct(
        public readonly TestCase $test,
        public Competition $competition,
        public readonly Organization $issuerOrganization,
        public readonly User $issuer,
        public readonly array $participants,
        public readonly array $bidders,
    ) {}

    /**
     * @param  (Closure(CompetitionFactory): CompetitionFactory)|null  $state  e.g. fn ($f) => $f->withoutFinalWindow()->live()
     * @param  array<string, mixed>  $attributes  competition columns
     */
    public static function make(TestCase $test, ?Closure $state = null, int $bidders = 2, array $attributes = []): self
    {
        $issuerOrganization = Organization::factory()->auctionEnabled()->create();
        $issuer = User::factory()->withMembership($issuerOrganization, OrgRole::Owner)->create();

        $factory = Competition::factory();
        $factory = $state !== null ? $state($factory) : $factory->withoutFinalWindow()->live();

        $competition = $factory->create([
            'organization_id' => $issuerOrganization->id,
            'created_by_user_id' => $issuer->id,
            ...$attributes,
        ]);

        $participants = [];
        $users = [];

        for ($i = 0; $i < $bidders; $i++) {
            $participant = Participant::factory()->create(['competition_id' => $competition->id]);
            $participants[] = $participant;
            $users[] = User::query()->findOrFail($participant->joined_by_user_id);
        }

        return new self($test, $competition, $issuerOrganization, $issuer, $participants, $users);
    }

    public function participant(int $index): Participant
    {
        return $this->participants[$index];
    }

    public function bidder(int $index): User
    {
        return $this->bidders[$index];
    }

    public function refresh(): Competition
    {
        return $this->competition = Competition::query()->findOrFail($this->competition->id);
    }

    public function actingAs(User $user): self
    {
        Auth::forgetGuards();
        Sanctum::actingAs($user);

        return $this;
    }

    /**
     * POST an offer as bidder `$index`. The per-participant rate limit (1 per 2 s) is cleared
     * first unless `$keepRateLimit`.
     *
     * @param  array<string, mixed>  $extra
     */
    public function offer(int $index, mixed $amount, ?string $key = null, array $extra = [], bool $keepRateLimit = false): TestResponse
    {
        if (! $keepRateLimit) {
            Cache::forget('offer-rate:'.$this->participants[$index]->id);
        }

        $this->actingAs($this->bidders[$index]);

        return $this->test->postJson(
            $this->url('offers'),
            ['amount_minor' => $amount, ...$extra],
            ['Idempotency-Key' => $key ?? (string) Str::ulid()],
        );
    }

    public function url(string $path = ''): string
    {
        return '/api/app/v1/competitions/'.$this->competition->public_id.($path === '' ? '' : '/'.$path);
    }

    public function lastOffer(): ?Offer
    {
        return Offer::query()->where('competition_id', $this->competition->id)->orderByDesc('seq')->first();
    }
}
