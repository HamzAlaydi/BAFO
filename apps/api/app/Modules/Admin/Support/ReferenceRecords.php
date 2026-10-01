<?php

declare(strict_types=1);

namespace App\Modules\Admin\Support;

use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Catalog\Models\Region;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The reference records the panel edits directly (§16 CRUD: plans, coupons and the lookups):
 * their audit noun, their audit subject, and whether other rows still point at them.
 */
final class ReferenceRecords
{
    /**
     * @var array<class-string<Model>, string>
     */
    private const array NOUNS = [
        Plan::class => 'plan',
        Coupon::class => 'coupon',
        Region::class => 'region',
        Category::class => 'category',
        CloseReason::class => 'close_reason',
        CompetitionPreset::class => 'competition_preset',
    ];

    /**
     * The columns that reference each table (foreign keys, soft-deleted rows included).
     *
     * @var array<class-string<Model>, list<array{0: string, 1: string}>>
     */
    private const array REFERENCES = [
        Plan::class => [['subscriptions', 'plan_id']],
        Coupon::class => [['coupon_redemptions', 'coupon_id'], ['payments', 'coupon_id'], ['competition_sponsorships', 'voucher_coupon_id']],
        Region::class => [['organizations', 'region_id'], ['vendors', 'region_id'], ['competitions', 'region_id']],
        Category::class => [['organization_category', 'category_id'], ['vendor_category', 'category_id'], ['competitions', 'category_id']],
        CloseReason::class => [
            ['competitions', 'cancel_reason_id'],
            ['competitions', 'not_awarded_reason_id'],
            ['awards', 'justification_reason_id'],
            ['offer_voids', 'reason_id'],
        ],
        CompetitionPreset::class => [],
    ];

    public static function noun(Model $record): string
    {
        return self::NOUNS[$record::class] ?? throw new InvalidArgumentException('Not a reference record: '.$record::class);
    }

    /**
     * A record still referenced elsewhere is deactivated, never deleted: deleting it would fail
     * on a restrict key or silently cascade (category pivots, coupon redemptions).
     */
    public static function isInUse(Model $record): bool
    {
        foreach (self::REFERENCES[$record::class] ?? [] as [$table, $column]) {
            if (DB::table($table)->where($column, $record->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The audit subject and meta. Models without a §4.9 morph alias (plans and the lookups)
     * cannot be an audit subject under `requireMorphMap()`, so they are named in the meta.
     *
     * @return array{0: Model|null, 1: array<string, mixed>}
     */
    public static function auditSubject(Model $record): array
    {
        if (Relation::getMorphAlias($record::class) !== $record::class) {
            return [$record, []];
        }

        return [null, array_filter([
            'record' => self::noun($record),
            'id' => $record->getAttribute('public_id'),
            'code' => $record->getAttribute('code'),
        ], static fn (mixed $value): bool => $value !== null)];
    }
}
