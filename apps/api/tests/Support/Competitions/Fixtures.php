<?php

declare(strict_types=1);

namespace Tests\Support\Competitions;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Database\Factories\ApiKeyFactory;
use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use App\Support\Auth\CurrentActor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

/**
 * Builders shared by the Competitions tests.
 */
final class Fixtures
{
    /**
     * An organization with a current paid subscription (AccessPolicy::canIssue) and its owner.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{0: Organization, 1: User}
     */
    public static function issuer(array $attributes = [], bool $plan = true): array
    {
        $organization = Organization::factory()->create($attributes);
        $owner = User::factory()->withMembership($organization, OrgRole::Owner)->create();

        if ($plan) {
            self::subscribe($organization);
        }

        return [$organization, $owner];
    }

    /**
     * A supplier organization and its owner, with or without a plan.
     *
     * @return array{0: Organization, 1: User}
     */
    public static function supplier(bool $plan = true): array
    {
        return self::issuer([], $plan);
    }

    public static function subscribe(Organization $organization): Subscription
    {
        return Subscription::factory()->create(['organization_id' => $organization->id]);
    }

    public static function member(Organization $organization, OrgRole $role = OrgRole::Member, bool $canAward = false): User
    {
        $user = User::factory()->withMembership($organization, $role)->create();

        Membership::query()->where('user_id', $user->id)->update(['can_award' => $canAward]);

        return $user->refresh();
    }

    public static function signIn(User $user): User
    {
        Auth::forgetGuards();
        CurrentActor::clear();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * A draft live tender of the organization, created by `$creator`, opening tomorrow for two days.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function draft(Organization $organization, ?User $creator = null, array $attributes = []): Competition
    {
        $opens = CarbonImmutable::now()->addDay()->startOfHour();

        return Competition::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $creator?->id,
            'category_id' => Category::factory(),
            'region_id' => Region::factory(),
            'bidding_opens_at' => $opens,
            'scheduled_close_at' => $opens->addDays(2),
            ...$attributes,
        ]);
    }

    /**
     * Draft invitations of known supplier organizations.
     *
     * @return list<Invitation>
     */
    public static function draftInvitations(Competition $competition, int $count = 2): array
    {
        $invitations = [];

        for ($i = 0; $i < $count; $i++) {
            [$supplier] = self::supplier();
            $invitations[] = Invitation::factory()->forOrganization($supplier)->create(['competition_id' => $competition->id]);
        }

        return $invitations;
    }

    /**
     * A sent invitation of a supplier organization (with a plan unless `$plan` is false).
     *
     * @return array{0: Invitation, 1: Organization, 2: User}
     */
    public static function invitee(Competition $competition, bool $plan = true, InvitationStatus $status = InvitationStatus::Sent, ?string $token = null): array
    {
        [$organization, $owner] = self::supplier($plan);

        $factory = Invitation::factory()->forOrganization($organization);
        $invitation = match ($status) {
            InvitationStatus::Viewed => $factory->viewed()->create(['competition_id' => $competition->id]),
            InvitationStatus::Declined => $factory->declined()->create(['competition_id' => $competition->id]),
            InvitationStatus::Expired => $factory->expired()->create(['competition_id' => $competition->id]),
            InvitationStatus::Revoked => $factory->revoked()->create(['competition_id' => $competition->id]),
            InvitationStatus::Draft => $factory->create(['competition_id' => $competition->id]),
            default => $factory->sent($token)->create(['competition_id' => $competition->id]),
        };

        return [$invitation, $organization, $owner];
    }

    /**
     * A joined participant organization and its owner.
     *
     * @return array{0: Participant, 1: Organization, 2: User}
     */
    public static function participant(Competition $competition): array
    {
        [$organization, $owner] = self::supplier();
        $participant = Participant::factory()->create([
            'competition_id' => $competition->id,
            'organization_id' => $organization->id,
            'joined_by_user_id' => $owner->id,
        ]);

        return [$participant, $organization, $owner];
    }

    /**
     * A bearer header for an API key of the organization with the given scopes.
     *
     * @param  list<ApiScope>  $scopes
     * @return array<string, string>
     */
    public static function apiHeaders(Organization $organization, array $scopes): array
    {
        $organization->forceFill(['api_enabled' => true])->save();

        $client = ApiClient::factory()->scopes($scopes)->create(['organization_id' => $organization->id]);
        $plainKey = ApiKeyFactory::makePlainKey();
        ApiKey::factory()->withPlainKey($plainKey)->create(['api_client_id' => $client->id]);

        Auth::forgetGuards();
        CurrentActor::clear();

        return ['Authorization' => 'Bearer '.$plainKey, 'Idempotency-Key' => 'test-'.bin2hex(random_bytes(8))];
    }

    /**
     * A valid create body (API.md §1.4) for a live tender with the given lookups.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function createBody(Category $category, Region $region, array $overrides = []): array
    {
        $opens = CarbonImmutable::now()->addDay()->startOfHour();

        return [
            'title' => 'توريد أجهزة حاسب محمول',
            'description' => 'نطاق العمل وفق كراسة الشروط.',
            'category_id' => $category->public_id,
            'region_id' => $region->public_id,
            'direction' => 'tender',
            'format' => 'live',
            'rules' => [
                'start_price_minor' => 25_000_000,
                'min_step_bps' => 50,
                'must_beat' => 'own',
                'rank_visibility' => 'leading_flag',
                'show_prices' => false,
                'auto_extend' => ['enabled' => true, 'window_seconds' => 180, 'by_seconds' => 180, 'max_extensions' => 10],
                'final_window_minutes' => 60,
                'bafo_round' => ['enabled' => false, 'duration_minutes' => null],
                'min_participants' => 2,
                'result_publication' => 'outcome_only',
            ],
            'bidding_opens_at' => $opens->toIso8601String(),
            'scheduled_close_at' => $opens->addDays(2)->toIso8601String(),
            ...$overrides,
        ];
    }
}
