<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Support\Auth\Actor;
use App\Support\Auth\CurrentActor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * Writes audit_logs entries (ARCHITECTURE §4.5). Every state-changing Action calls it inside
 * its transaction; offer acceptance and read endpoints do not.
 *
 *     AuditLogger::log('competition.published', $competition);
 *     AuditLogger::log('member.updated', $membership, AuditLogger::diff($membership));
 *     AuditLogger::log('competition.closed', $competition, actor: Actor::system());
 *
 * Action names are `<noun>.<verb_past>`. The organization whose feed shows the entry is, in
 * order: the explicit $organizationId, the actor's organization, the subject's
 * `organization_id`. Secrets in changes and meta are redacted.
 */
final class AuditLogger
{
    public const string REDACTED = '[redacted]';

    /**
     * Keys whose values never reach the audit log (matched case-insensitively, at any depth).
     */
    private const string SENSITIVE_KEYS = '/(password|secret|token|api_key|key_hash|code_hash|otp|credential)/i';

    /**
     * @param  array<string, mixed>  $changes  {field: {from, to}}
     * @param  array<string, mixed>  $meta
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        array $changes = [],
        array $meta = [],
        ?Actor $actor = null,
        // CONTRACT-GAP: not in the §4.5 signature. The feed organization is usually the actor's
        // or the subject's; this optional override covers the cases where it is neither.
        ?int $organizationId = null,
    ): void {
        $actor ??= CurrentActor::get();

        $publicId = $subject?->getAttribute('public_id');

        AuditLog::query()->create([
            'occurred_at' => Date::now(),
            'organization_id' => $organizationId ?? $actor->organizationId ?? self::subjectOrganizationId($subject),
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'actor_label' => $actor->label,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject !== null && is_numeric($subject->getKey()) ? (int) $subject->getKey() : null,
            'subject_public_id' => is_string($publicId) ? $publicId : null,
            'changes' => $changes === [] ? null : self::redact($changes),
            'meta' => $meta === [] ? null : self::redact($meta),
            'channel' => $actor->channel->value,
            'ip' => $actor->ip,
            'user_agent' => $actor->userAgent,
            'request_id' => $actor->requestId,
        ]);
    }

    /**
     * The `{field: {from, to}}` map of the model's last save (Model::getChanges() against
     * Model::getPrevious()). updated_at is left out.
     *
     * @param  list<string>  $only  limit to these attributes
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function diff(Model $model, array $only = []): array
    {
        $previous = $model->getPrevious();
        $diff = [];

        foreach ($model->getChanges() as $field => $to) {
            if ($field === $model->getUpdatedAtColumn() || ($only !== [] && ! in_array($field, $only, true))) {
                continue;
            }

            $diff[$field] = ['from' => $previous[$field] ?? null, 'to' => $to];
        }

        return $diff;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public static function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEYS, $key) === 1) {
                $values[$key] = is_array($value) && (array_key_exists('from', $value) || array_key_exists('to', $value))
                    ? array_map(static fn (): string => self::REDACTED, $value)
                    : self::REDACTED;
            } elseif (is_array($value)) {
                $values[$key] = self::redact($value);
            }
        }

        return $values;
    }

    private static function subjectOrganizationId(?Model $subject): ?int
    {
        $organizationId = $subject?->getAttribute('organization_id');

        return is_numeric($organizationId) ? (int) $organizationId : null;
    }
}
