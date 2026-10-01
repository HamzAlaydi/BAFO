<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Broadcasting;

use App\Support\Http\Iso;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\Date;

/**
 * `competition.updated` on the issuer channel and every participant channel (ARCHITECTURE §9.3,
 * API.md §5): `{competition_id, fields, server_time}`. Clients refetch the competition.
 */
final class CompetitionUpdatedBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    public string $queue = 'live';

    /**
     * @param  list<string>  $channels  channel names without the `private-` prefix
     * @param  list<string>  $fields
     */
    public function __construct(
        public readonly string $competitionId,
        public readonly array $channels,
        public readonly array $fields,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(static fn (string $name): PrivateChannel => new PrivateChannel($name), $this->channels);
    }

    public function broadcastAs(): string
    {
        return 'competition.updated';
    }

    /**
     * @return array{competition_id: string, fields: list<string>, server_time: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'competition_id' => $this->competitionId,
            'fields' => $this->fields,
            'server_time' => Iso::format(Date::now()),
        ];
    }
}
