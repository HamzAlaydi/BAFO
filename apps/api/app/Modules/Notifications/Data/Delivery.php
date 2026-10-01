<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Data;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Enums\DeliveryChannel;

/**
 * One recipient of a notification, minus the catalogue channels that do not apply to them
 * (for example mail for the participants of `competition.closed`, or push while the
 * participant is on the live screen).
 */
final readonly class Delivery
{
    /**
     * @param  list<DeliveryChannel>  $without
     */
    public function __construct(
        public User $user,
        public array $without = [],
    ) {}

    /**
     * @param  iterable<User>  $users
     * @param  list<DeliveryChannel>  $without
     * @return list<self>
     */
    public static function toEach(iterable $users, array $without = []): array
    {
        $deliveries = [];

        foreach ($users as $user) {
            $deliveries[] = new self($user, $without);
        }

        return $deliveries;
    }
}
