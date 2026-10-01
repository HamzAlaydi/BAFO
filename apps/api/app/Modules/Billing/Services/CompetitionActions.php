<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Competitions\Models\Competition;
use App\Support\Auth\Actor;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * Calls the Competitions Actions that Billing runs after a sponsorship payment
 * (ARCHITECTURE §3.6, §13.5):
 *
 *   PublishCompetition::handle(Competition $c, Actor $actor): Competition
 *   InviteParticipants::handle(Competition $c, array $rows, Actor $actor): Collection<Invitation>
 *
 * The classes are resolved by name from the container, so Billing does not depend on the
 * Competitions implementation at compile time (the modules are built in parallel), and tests
 * bind doubles under the same names.
 */
final readonly class CompetitionActions
{
    public const string PUBLISH = 'App\\Modules\\Competitions\\Actions\\PublishCompetition';

    public const string INVITE = 'App\\Modules\\Competitions\\Actions\\InviteParticipants';

    public function __construct(private Container $container) {}

    public function publish(Competition $competition, Actor $actor): void
    {
        ($this->handler(self::PUBLISH))($competition, $actor);
    }

    /**
     * @param  list<array{email: string, name?: string|null, organization_id?: int|null, vendor_id?: int|null, sponsored: bool}>  $rows
     */
    public function invite(Competition $competition, array $rows, Actor $actor): void
    {
        ($this->handler(self::INVITE))($competition, $rows, $actor);
    }

    private function handler(string $class): callable
    {
        if (! $this->container->bound($class) && ! class_exists($class)) {
            throw new RuntimeException("The Competitions action [{$class}] is not available.");
        }

        $handler = [$this->container->make($class), 'handle'];

        if (! is_callable($handler)) {
            throw new RuntimeException("The Competitions action [{$class}] has no handle() method.");
        }

        return $handler;
    }
}
