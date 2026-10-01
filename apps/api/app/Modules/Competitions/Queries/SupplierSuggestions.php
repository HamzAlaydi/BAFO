<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Queries;

use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Http\Resources\Shapes;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Models\Organization;
use Illuminate\Database\Eloquent\Builder;

/**
 * Supplier suggestions for inviting (API.md §1.4 `GET …/suggestions`): active organizations
 * that opted in (`visible_in_suggestions`) and share the category or the region, except the
 * issuer and organizations already invited. Category-and-region matches first, then name.
 */
final readonly class SupplierSuggestions
{
    public function __construct(private AccessPolicy $access) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function find(Competition $competition, ?string $q, ?int $categoryId, ?int $regionId, int $limit): array
    {
        $categoryId ??= $competition->category_id;
        $regionId ??= $competition->region_id;

        $invited = Invitation::query()
            ->select('organization_id')
            ->where('competition_id', $competition->id)
            ->whereNotNull('organization_id')
            ->where('status', '!=', InvitationStatus::Revoked->value);

        $categoryMatch = static fn (Builder $query): Builder => $query->whereHas(
            'categories',
            static fn (Builder $c): Builder => $c->where('categories.id', $categoryId),
        );

        $organizations = Organization::query()
            ->with(['region', 'categories', 'logoFile'])
            ->where('status', OrganizationStatus::Active->value)
            ->where('visible_in_suggestions', true)
            ->whereKeyNot($competition->organization_id)
            ->whereNotIn('id', $invited)
            ->where(static function (Builder $query) use ($categoryMatch, $regionId): void {
                $categoryMatch($query)->orWhere('region_id', $regionId);
            })
            ->when($q !== null && trim($q) !== '', static fn (Builder $query) => $query->where('name', 'ilike', '%'.addcslashes(trim((string) $q), '%_\\').'%'))
            ->withExists(['categories as category_match' => static fn (Builder $c): Builder => $c->where('categories.id', $categoryId)])
            ->orderByRaw('(case when exists (select 1 from organization_category oc where oc.organization_id = organizations.id and oc.category_id = ?) then 1 else 0 end + case when organizations.region_id = ? then 1 else 0 end) desc', [$categoryId, $regionId])
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $organizations->map(fn (Organization $organization): array => [
            'organization' => Shapes::organization($organization),
            'region' => Shapes::region($organization->region),
            'categories' => $organization->categories->map(static fn (Category $category): ?array => Shapes::category($category))->values()->all(),
            'has_active_plan' => $this->access->canIssue($organization),
            'match' => [
                'category' => (bool) $organization->getAttribute('category_match'),
                'region' => $organization->region_id === $regionId,
            ],
        ])->values()->all();
    }

    public static function categoryId(?string $publicId): ?int
    {
        return $publicId === null ? null : Category::query()->where('public_id', strtolower($publicId))->value('id');
    }

    public static function regionId(?string $publicId): ?int
    {
        return $publicId === null ? null : Region::query()->where('public_id', strtolower($publicId))->value('id');
    }
}
