<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use App\Modules\Competitions\Models\Competition;
use App\Support\Auth\Actor;
use Throwable;

/**
 * Records the PublishCompetition / InviteParticipants calls Billing makes after a sponsorship
 * payment (ARCHITECTURE §13.5), without running the Competitions module.
 */
final class CompetitionActionsSpy
{
    /**
     * @var list<array{competition_id: int, actor: Actor}>
     */
    public array $published = [];

    /**
     * @var list<array{competition_id: int, rows: array<int, mixed>, actor: Actor}>
     */
    public array $invited = [];

    public function __construct(private readonly ?Throwable $fail = null) {}

    public function publisher(): object
    {
        $spy = $this;

        return new class($spy)
        {
            public function __construct(private readonly CompetitionActionsSpy $spy) {}

            public function handle(Competition $competition, Actor $actor): Competition
            {
                $this->spy->recordPublish($competition, $actor);

                return $competition;
            }
        };
    }

    public function inviter(): object
    {
        $spy = $this;

        return new class($spy)
        {
            public function __construct(private readonly CompetitionActionsSpy $spy) {}

            /**
             * @param  array<int, mixed>  $rows
             * @return list<mixed>
             */
            public function handle(Competition $competition, array $rows, Actor $actor): array
            {
                $this->spy->recordInvite($competition, $rows, $actor);

                return [];
            }
        };
    }

    public function recordPublish(Competition $competition, Actor $actor): void
    {
        if ($this->fail !== null) {
            throw $this->fail;
        }

        $this->published[] = ['competition_id' => $competition->id, 'actor' => $actor];
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    public function recordInvite(Competition $competition, array $rows, Actor $actor): void
    {
        if ($this->fail !== null) {
            throw $this->fail;
        }

        $this->invited[] = ['competition_id' => $competition->id, 'rows' => $rows, 'actor' => $actor];
    }
}
