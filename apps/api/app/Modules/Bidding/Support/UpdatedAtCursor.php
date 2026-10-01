<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Support;

use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Keyset pagination over `updated_at, public_id` ascending for public v1 lists (API.md §0.5:
 * "Ordering is stable: updated_at, id ascending"), with an opaque cursor.
 *
 * CONTRACT-GAP: Laravel's cursorPaginate() writes timestamp cursor values through
 * Carbon::__toString(), which drops the microseconds of the precise (tstz6) tables, so the next
 * page repeats rows that share a second. This cursor keeps the microseconds and uses the public
 * id as the tie-breaker (internal ids never leave the server).
 */
final class UpdatedAtCursor
{
    private const string FORMAT = 'Y-m-d H:i:s.uP';

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array{items: Collection<int, TModel>, pagination: array{type: string, per_page: int, next_cursor: string|null, prev_cursor: null, has_more: bool}}
     */
    public static function paginate(Builder $query, int $perPage, ?string $cursor): array
    {
        $model = $query->getModel();
        $updatedAt = $model->qualifyColumn('updated_at');
        $publicId = $model->qualifyColumn('public_id');

        if ($cursor !== null && $cursor !== '') {
            [$after, $afterId] = self::decode($cursor);

            $query->where(static function (Builder $q) use ($updatedAt, $publicId, $after, $afterId): void {
                $q->where($updatedAt, '>', $after)
                    ->orWhere(static fn (Builder $tie) => $tie->where($updatedAt, '=', $after)->where($publicId, '>', $afterId));
            });
        }

        $rows = $query->orderBy($updatedAt)->orderBy($publicId)->limit($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        $items = $rows->take($perPage)->values();
        $last = $items->last();

        return [
            'items' => $items,
            'pagination' => [
                'type' => 'cursor',
                'per_page' => $perPage,
                'next_cursor' => $hasMore && $last !== null ? self::encode($last) : null,
                'prev_cursor' => null,
                'has_more' => $hasMore,
            ],
        ];
    }

    private static function encode(Model $model): string
    {
        $updatedAt = $model->getAttribute('updated_at');
        $at = $updatedAt instanceof \DateTimeInterface ? CarbonImmutable::instance($updatedAt)->utc()->format(self::FORMAT) : '';

        return rtrim(strtr(base64_encode((string) json_encode(['u' => $at, 'p' => $model->getAttribute('public_id')])), '+/', '-_'), '=');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function decode(string $cursor): array
    {
        try {
            $decoded = json_decode((string) base64_decode(strtr($cursor, '-_', '+/'), true), true, 4, JSON_THROW_ON_ERROR);
            $at = is_array($decoded) && is_string($decoded['u'] ?? null) ? CarbonImmutable::createFromFormat(self::FORMAT, $decoded['u']) : null;
            $publicId = is_array($decoded) && is_string($decoded['p'] ?? null) ? $decoded['p'] : null;
        } catch (Throwable) {
            $at = $publicId = null;
        }

        if (! $at instanceof CarbonImmutable || $publicId === null) {
            throw new ApiException('validation_failed', status: 422, errors: ['cursor' => [(string) __('bidding.validation.cursor_invalid')]]);
        }

        return [$at->format(self::FORMAT), $publicId];
    }
}
