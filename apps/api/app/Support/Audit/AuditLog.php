<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Support\Auth\ActorType;
use App\Support\Database\Concerns\UsesPreciseTimestamps;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One row of the append-only audit_logs table (ARCHITECTURE §4.5, §5.1). Written only by
 * AuditLogger; a database trigger rejects UPDATE and DELETE.
 *
 * @property int $id
 * @property CarbonImmutable $occurred_at
 * @property int|null $organization_id
 * @property ActorType $actor_type
 * @property int|null $actor_id
 * @property string|null $actor_label
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $subject_public_id
 * @property array<string, mixed>|null $changes
 * @property array<string, mixed>|null $meta
 * @property string|null $channel
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $request_id
 */
final class AuditLog extends Model
{
    use UsesPreciseTimestamps;

    public $timestamps = false;

    protected $table = 'audit_logs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'occurred_at',
        'organization_id',
        'actor_type',
        'actor_id',
        'actor_label',
        'action',
        'subject_type',
        'subject_id',
        'subject_public_id',
        'changes',
        'meta',
        'channel',
        'ip',
        'user_agent',
        'request_id',
    ];

    /**
     * @param  Builder<AuditLog>  $query
     * @param  list<string>  $actions
     * @return Builder<AuditLog>
     */
    public function scopeForOrganization(Builder $query, int $organizationId, array $actions = []): Builder
    {
        return $query->where('organization_id', $organizationId)
            ->when($actions !== [], static fn (Builder $q) => $q->whereIn('action', $actions))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'actor_type' => ActorType::class,
            'changes' => 'array',
            'meta' => 'array',
        ];
    }
}
