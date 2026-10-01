<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A stored `Idempotency-Key` and the response it produced (table `idempotency_keys`,
 * ARCHITECTURE §4.8). `response_status` null means the first request is still running.
 * Rows expire after 24 h; `platform:prune` deletes them.
 *
 * @property int $id
 * @property string $scope_type
 * @property int $scope_id
 * @property string $key
 * @property string $method
 * @property string $path
 * @property string $request_hash
 * @property int|null $response_status
 * @property string|null $response_body raw JSON of the stored response body
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 */
final class IdempotencyKey extends Model
{
    public const string SCOPE_USER = 'user';

    public const string SCOPE_API_CLIENT = 'api_client';

    public const int TTL_HOURS = 24;

    public const UPDATED_AT = null;

    protected $table = 'idempotency_keys';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'scope_type',
        'scope_id',
        'key',
        'method',
        'path',
        'request_hash',
        'response_status',
        'response_body',
        'expires_at',
    ];

    public function isCompleted(): bool
    {
        return $this->response_status !== null;
    }

    /**
     * @param  Builder<IdempotencyKey>  $query
     * @return Builder<IdempotencyKey>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_id' => 'integer',
            'response_status' => 'integer',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
