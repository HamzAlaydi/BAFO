<?php

declare(strict_types=1);

namespace App\Support\Database\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Public identifier for models: a lowercase ULID in the `public_id` column.
 *
 * The bigint primary key stays internal; APIs, route binding and realtime channel
 * names use `public_id`. Resources expose it as "id". Migration column:
 *
 *     $table->ulid('public_id')->unique();
 *
 * @mixin Model
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(static function (Model $model): void {
            /** @var Model&self $model */
            $column = $model->getPublicIdColumn();

            if (blank($model->getAttribute($column))) {
                $model->setAttribute($column, self::newPublicId());
            }
        });
    }

    public static function newPublicId(): string
    {
        return strtolower((string) Str::ulid());
    }

    public function getPublicIdColumn(): string
    {
        return 'public_id';
    }

    public function getPublicId(): string
    {
        return (string) $this->getAttribute($this->getPublicIdColumn());
    }

    public function getRouteKeyName(): string
    {
        return $this->getPublicIdColumn();
    }

    /**
     * Route binding accepts any ULID casing and rejects malformed ids before querying.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field === null || $field === $this->getPublicIdColumn()) {
            if (! is_string($value) || ! Str::isUlid($value)) {
                return null;
            }

            $value = strtolower($value);
        }

        return parent::resolveRouteBinding($value, $field);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWherePublicId(Builder $query, string $publicId): Builder
    {
        return $query->where($this->qualifyColumn($this->getPublicIdColumn()), strtolower($publicId));
    }

    public static function findByPublicId(string $publicId): ?static
    {
        if (! Str::isUlid($publicId)) {
            return null;
        }

        return static::query()->wherePublicId($publicId)->first();
    }

    public static function findByPublicIdOrFail(string $publicId): static
    {
        return static::findByPublicId($publicId)
            ?? throw (new ModelNotFoundException)->setModel(static::class, [$publicId]);
    }
}
