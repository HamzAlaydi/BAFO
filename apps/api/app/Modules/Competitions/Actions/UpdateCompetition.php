<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Competitions\Data\CompetitionInput;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Events\CompetitionUpdated;
use App\Modules\Competitions\Jobs\CloseCompetition;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\CompetitionComposer;
use App\Modules\Competitions\Services\CompetitionExternalRefs;
use App\Modules\Competitions\Services\RulesMapper;
use App\Modules\Competitions\Services\RulesValidator;
use App\Modules\Competitions\Services\ScheduleCalculator;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * `PATCH /competitions/{competition}` (API.md §1.4, §3.4). Rules are locked at publish (D6):
 *
 *   draft      everything accepted by create
 *   scheduled  title, description, category, region and the schedule (derived times are
 *              recomputed and R16 is re-checked at publish strictness)
 *   live       title and description
 *   others     nothing
 *
 * A field not allowed in the current status is 409 `competition_not_editable` with
 * `details.fields`.
 */
final readonly class UpdateCompetition
{
    /**
     * Request fields accepted per status (app field names and their public API variants).
     *
     * CONTRACT-GAP: `external_refs` (public API) is ERP metadata, accepted in every status.
     *
     * @var array<string, list<string>>
     */
    private const array EDITABLE = [
        'scheduled' => ['title', 'description', 'category_id', 'category_code', 'category_other_text', 'region_id',
            'region_code', 'bidding_opens_at', 'scheduled_close_at', 'external_refs'],
        'live' => ['title', 'description', 'external_refs'],
    ];

    /**
     * Column => the realtime field group of `competition.updated` (API.md §5).
     *
     * @var array<string, string>
     */
    private const array FIELD_GROUPS = [
        'title' => 'title',
        'description' => 'description',
        'bidding_opens_at' => 'schedule',
        'scheduled_close_at' => 'schedule',
        'category_id' => 'category',
        'category_other_text' => 'category',
        'region_id' => 'region',
        'direction' => 'rules',
        'format' => 'rules',
        'preset_code' => 'rules',
    ];

    public function __construct(
        private CompetitionComposer $composer,
        private RulesValidator $validator,
        private ScheduleCalculator $schedule,
        private CompetitionExternalRefs $externalRefs,
    ) {}

    public function handle(Competition $competition, CompetitionInput $input, Actor $actor): Competition
    {
        return DB::transaction(function () use ($competition, $input, $actor): Competition {
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();

            $this->assertEditable($locked, $input);

            if ($locked->status === CompetitionStatus::Draft) {
                $this->composer->compose($locked, $input, creating: false);
            } else {
                $locked->forceFill(array_diff_key($input->columns, RulesMapper::PATHS));

                if ($locked->status === CompetitionStatus::Scheduled) {
                    $this->validator->validateSave($locked);

                    if ($locked->isDirty(['bidding_opens_at', 'scheduled_close_at'])) {
                        $this->rescheduleScheduled($locked);
                    }
                }
            }

            $fields = $this->changedFieldGroups($locked);
            $changes = [];

            if ($locked->isDirty()) {
                $locked->save();
                $changes = AuditLogger::diff($locked);
            }

            if ($input->externalRefs !== null) {
                $this->externalRefs->replace($locked, $input->externalRefs, $actor);
            }

            if ($fields !== []) {
                AuditLogger::log('competition.updated', $locked, $changes, actor: $actor, organizationId: $locked->organization_id);

                event(new CompetitionUpdated($locked, $fields, $actor));
            }

            return $locked;
        });
    }

    private function assertEditable(Competition $competition, CompetitionInput $input): void
    {
        if ($competition->status === CompetitionStatus::Draft) {
            return;
        }

        $allowed = self::EDITABLE[$competition->status->value] ?? [];
        $refused = array_values(array_diff($input->fields, $allowed));

        if ($refused !== [] || ($allowed === [] && $input->fields !== [])) {
            throw self::notEditable($refused === [] ? $input->fields : $refused);
        }
    }

    /**
     * @param  list<string>  $fields
     */
    public static function notEditable(array $fields = []): ApiException
    {
        return new ApiException(
            errorCode: 'competition_not_editable',
            messageKey: 'competitions.errors.competition_not_editable',
            status: 409,
            details: $fields === [] ? [] : ['fields' => $fields],
        );
    }

    /**
     * A schedule change while scheduled: publish-time R16, new derived times, a new close job.
     */
    private function rescheduleScheduled(Competition $competition): void
    {
        $now = Date::now();

        if ($competition->bidding_opens_at === null) {
            $message = __('competitions.validation.opens_required');

            throw ValidationException::withMessages([
                'bidding_opens_at' => [is_string($message) ? $message : 'opens_required'],
            ]);
        }

        $this->validator->validateSchedule($competition, $now);
        $this->schedule->derive($competition, $competition->bidding_opens_at);

        CloseCompetition::scheduleFor($competition);
    }

    /**
     * @return list<string>
     */
    private function changedFieldGroups(Competition $competition): array
    {
        $groups = [];

        foreach (array_keys($competition->getDirty()) as $column) {
            $group = self::FIELD_GROUPS[$column] ?? (array_key_exists($column, RulesMapper::PATHS) ? 'rules' : null);

            if ($group !== null && ! in_array($group, $groups, true)) {
                $groups[] = $group;
            }
        }

        return $groups;
    }
}
