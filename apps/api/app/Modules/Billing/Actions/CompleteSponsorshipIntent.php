<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\SponsorshipIntent;
use App\Modules\Billing\Events\SponsorshipPublishFailed;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Services\CompetitionActions;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Auth\ActorType;
use App\Support\Auth\Channel;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Runs, after the fulfilment commit, what a sponsorship payment paid for (ARCHITECTURE §13.5):
 *
 * - `intent: publish` → `PublishCompetition` (still a draft only);
 * - `intent: invite`  → `InviteParticipants` with the stored rows, all sponsored.
 *
 * The actor is the payer with channel `system`. When the Competitions action refuses, the
 * funded passes (or slots) stay and SponsorshipPublishFailed carries the error code: the
 * issuer fixes the problem and tries again without paying again.
 */
final readonly class CompleteSponsorshipIntent
{
    public function __construct(private CompetitionActions $competitions) {}

    public function handle(Payment $payment): void
    {
        $competitionId = $payment->metadata['competition_id'] ?? null;
        $competition = is_numeric($competitionId) ? Competition::query()->find((int) $competitionId) : null;
        $sponsorship = $competition?->sponsorship;

        if ($competition === null || ! $sponsorship instanceof CompetitionSponsorship) {
            return;
        }

        $intent = SponsorshipIntent::tryFrom((string) ($payment->metadata['intent'] ?? ''));
        $actor = $this->payerActor($payment);

        try {
            if ($intent === SponsorshipIntent::Publish && $competition->status === CompetitionStatus::Draft) {
                $this->competitions->publish($competition, $actor);
            } elseif ($intent === SponsorshipIntent::Invite) {
                /** @var list<array{email: string, name?: string|null, organization_id?: int|null, vendor_id?: int|null, sponsored: bool}> $rows */
                $rows = array_values(array_filter((array) ($payment->metadata['rows'] ?? []), 'is_array'));
                $this->competitions->invite($competition, $rows, $actor);
            }
        } catch (Throwable $e) {
            $code = match (true) {
                $e instanceof ApiException => $e->errorCode,
                $e instanceof ValidationException => 'validation_failed',
                default => 'server_error',
            };

            if ($code === 'server_error') {
                report($e);
            }

            DB::transaction(static function () use ($sponsorship, $payment, $code, $actor, $intent): void {
                AuditLogger::log('sponsorship.publish_failed', $sponsorship, meta: [
                    'intent' => $intent->value,
                    'error_code' => $code,
                    'payment_id' => $payment->public_id,
                ], actor: $actor, organizationId: $payment->organization_id);

                SponsorshipPublishFailed::dispatch($sponsorship, $payment, $code);
            });
        }
    }

    /**
     * The payer acting through the system after the payment (§13.5: "actor = the payer user
     * (channel system)").
     */
    private function payerActor(Payment $payment): Actor
    {
        $payer = User::query()->find($payment->created_by_user_id);

        return new Actor(
            type: ActorType::User,
            id: $payment->created_by_user_id,
            organizationId: $payment->organization_id,
            userId: $payment->created_by_user_id,
            apiClientId: null,
            adminId: null,
            channel: Channel::System,
            label: $payer?->name,
        );
    }
}
