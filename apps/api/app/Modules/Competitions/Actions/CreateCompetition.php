<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Competitions\Data\CompetitionInput;
use App\Modules\Competitions\Enums\CompetitionSource;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Events\CompetitionCreated;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\CompetitionComposer;
use App\Modules\Competitions\Services\CompetitionExternalRefs;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Auth\Channel;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * Creates a draft (API.md §1.4 `POST /competitions`, §3.4 public `POST /competitions`).
 * R20: the issuer needs an active plan (403 `issuer_plan_required`); the rules pass the §7.2
 * save checks.
 */
final readonly class CreateCompetition
{
    public function __construct(
        private AccessPolicy $access,
        private CompetitionComposer $composer,
        private CompetitionExternalRefs $externalRefs,
    ) {}

    public function handle(Organization $organization, CompetitionInput $input, Actor $actor): Competition
    {
        if (! $this->access->canIssue($organization)) {
            throw self::planRequired();
        }

        return DB::transaction(function () use ($organization, $input, $actor): Competition {
            $competition = new Competition;
            $competition->forceFill([
                'organization_id' => $organization->id,
                'created_by_user_id' => $actor->userId,
                'created_by_api_client_id' => $actor->apiClientId,
                'source' => self::sourceOf($actor),
                'status' => CompetitionStatus::Draft,
                'currency' => 'SAR',
            ]);
            $competition->setRelation('organization', $organization);

            $this->composer->compose($competition, $input, creating: true);

            $competition->save();

            if ($input->externalRefs !== null) {
                $this->externalRefs->replace($competition, $input->externalRefs, $actor);
            }

            AuditLogger::log('competition.created', $competition, actor: $actor, organizationId: $organization->id);

            event(new CompetitionCreated($competition, $actor));

            return $competition;
        });
    }

    public static function planRequired(): ApiException
    {
        return new ApiException(
            errorCode: 'issuer_plan_required',
            messageKey: 'competitions.errors.issuer_plan_required',
            status: 403,
        );
    }

    private static function sourceOf(Actor $actor): CompetitionSource
    {
        return match ($actor->channel) {
            Channel::Api => CompetitionSource::Api,
            Channel::Ios => CompetitionSource::Ios,
            Channel::Android => CompetitionSource::Android,
            default => CompetitionSource::Web,
        };
    }
}
