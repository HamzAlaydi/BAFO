<?php

declare(strict_types=1);

namespace Tests\Support\Notifications;

use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Notifications\BafoNotification;
use App\Modules\Notifications\NotificationsServiceProvider;
use App\Support\Auth\Actor;
use Closure;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Testing\Fakes\NotificationFake;
use RuntimeException;

/**
 * Builders and probes for the Notifications tests.
 *
 *     $team = NotificationScenario::team();                  // owner, admin, member + an inactive member
 *     $competition = NotificationScenario::competition($team->organization);
 *     $bidder = NotificationScenario::participant($competition, locale: 'en');
 *     NotificationScenario::fire(CompetitionOpened::class, ['competition' => $competition]);
 *     NotificationScenario::sent(CompetitionOpenedNotification::class);  // [userId => [channels, locale]]
 */
final class NotificationScenario
{
    public const string IDENTITY = 'App\\Modules\\Identity\\Events\\';

    public const string COMPETITIONS = 'App\\Modules\\Competitions\\Events\\';

    public const string BIDDING = 'App\\Modules\\Bidding\\Events\\';

    public const string BILLING = 'App\\Modules\\Billing\\Events\\';

    public const string INTEGRATIONS = 'App\\Modules\\Integrations\\Events\\';

    /**
     * An organization with an owner, an admin (`billing.view`, `integrations.manage`), a member
     * (neither), and an inactive member who must never be notified.
     */
    public static function team(string $locale = 'ar'): Team
    {
        $organization = Organization::factory()->create();

        $owner = User::factory()->withMembership($organization, OrgRole::Owner)->create(['locale' => $locale]);
        $admin = User::factory()->withMembership($organization, OrgRole::Admin)->create(['locale' => $locale]);
        $member = User::factory()->withMembership($organization, OrgRole::Member)->create(['locale' => $locale]);
        $inactive = User::factory()->withMembership($organization, OrgRole::Member)->create(['locale' => $locale]);
        $inactive->membership?->update(['status' => MembershipStatus::Inactive]);

        return new Team($organization, $owner, $admin, $member, $inactive);
    }

    /**
     * A competition of the issuer, live with a final window by default.
     *
     * @param  (Closure(CompetitionFactory): CompetitionFactory)|null  $state
     * @param  array<string, mixed>  $attributes
     */
    public static function competition(Organization $issuer, ?Closure $state = null, array $attributes = []): Competition
    {
        $factory = Competition::factory();
        $factory = $state !== null ? $state($factory) : $factory->live();

        return $factory->create(['organization_id' => $issuer->id, ...$attributes]);
    }

    /**
     * A joined participant: a new organization whose owner (in `$locale`) joined.
     */
    public static function participant(Competition $competition, string $locale = 'ar', ?Organization $organization = null): Participant
    {
        $organization ??= Organization::factory()->create();
        $owner = $organization->ownerMembership?->user
            ?? User::factory()->withMembership($organization, OrgRole::Owner)->create(['locale' => $locale]);

        return Participant::factory()->create([
            'competition_id' => $competition->id,
            'organization_id' => $organization->id,
            'joined_by_user_id' => $owner->id,
        ]);
    }

    /**
     * The active users of the participant's organization.
     *
     * @return list<User>
     */
    public static function usersOf(Participant $participant): array
    {
        return array_values(User::query()
            ->whereHas('membership', static fn ($membership) => $membership->where('organization_id', $participant->organization_id))
            ->orderBy('id')
            ->get()
            ->all());
    }

    /**
     * Fires a domain event (ARCHITECTURE §10) at the Notifications listener bound to its
     * class-string, through a dispatcher that holds only that binding: the queued, after-commit
     * path runs as in production, while other modules' listeners (still being built) stay out.
     *
     * The real event class is used once its module has landed it, which also checks the §10
     * constructor; until then an object with the same public properties stands in.
     *
     * @param  array<string|int, mixed>  $properties  named §10 properties, or positional arguments
     */
    public static function fire(string $eventClass, array $properties): void
    {
        $listener = NotificationsServiceProvider::EVENT_LISTENERS[$eventClass]
            ?? throw new RuntimeException("No Notifications listener for [{$eventClass}].");

        $event = class_exists($eventClass) ? new $eventClass(...$properties) : (object) $properties;

        $dispatcher = new Dispatcher(app());
        $dispatcher->setQueueResolver(static fn (): QueueFactory => app(QueueFactory::class));
        $dispatcher->setTransactionManagerResolver(static fn (): mixed => app()->bound('db.transactions') ? app('db.transactions') : null);
        $dispatcher->listen($eventClass, $listener);
        $dispatcher->dispatch($eventClass, [$event]);
    }

    public static function actor(): Actor
    {
        return Actor::system();
    }

    /**
     * What the notification fake recorded for a notification class, keyed by user id.
     *
     * @param  class-string<BafoNotification>  $notificationClass
     * @return array<int, array{channels: list<string>, locale: string|null, notification: BafoNotification}>
     */
    public static function sent(string $notificationClass): array
    {
        $fake = Notification::getFacadeRoot();

        if (! $fake instanceof NotificationFake) {
            throw new RuntimeException('Call Notification::fake() first.');
        }

        $sent = [];

        foreach ($fake->sentNotifications()[User::class] ?? [] as $userId => $byClass) {
            foreach ($byClass[$notificationClass] ?? [] as $entry) {
                $sent[(int) $userId] = [
                    'channels' => array_values($entry['channels']),
                    'locale' => $entry['locale'] ?? null,
                    'notification' => $entry['notification'],
                ];
            }
        }

        ksort($sent);

        return $sent;
    }

    /**
     * @param  iterable<User>  $users
     * @return list<int>
     */
    public static function ids(iterable $users): array
    {
        $ids = [];

        foreach ($users as $user) {
            $ids[] = (int) $user->getKey();
        }

        sort($ids);

        return $ids;
    }
}
