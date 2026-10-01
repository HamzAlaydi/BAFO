<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers\AppV1;

use App\Modules\Integrations\Actions\Vendors\ArchiveVendor;
use App\Modules\Integrations\Actions\Vendors\CreateVendor;
use App\Modules\Integrations\Actions\Vendors\UpdateVendor;
use App\Modules\Integrations\Enums\VendorSource;
use App\Modules\Integrations\Enums\VendorStatus;
use App\Modules\Integrations\Http\Concerns\ResolvesTenant;
use App\Modules\Integrations\Http\Requests\StoreVendorRequest;
use App\Modules\Integrations\Http\Requests\UpdateVendorRequest;
use App\Modules\Integrations\Http\Resources\VendorResource;
use App\Modules\Integrations\Models\Vendor;
use App\Support\Auth\CurrentActor;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The vendor directory for the dashboard (API.md §1.9 "Vendors"): `competitions.create`, own
 * organization only.
 */
final class VendorController extends ApiController
{
    use ResolvesTenant;

    /**
     * Filters: `q` (name, English name, e-mail, CR), `status`, `category_id`, `region_id`.
     *
     * CONTRACT-GAP: without a `status` filter archived vendors are left out (DELETE archives).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Vendor::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', Rule::enum(VendorStatus::class)],
            'category_id' => ['nullable', 'string', 'max:26'],
            'region_id' => ['nullable', 'string', 'max:26'],
        ]);

        $vendors = Vendor::query()
            ->with(VendorResource::RELATIONS)
            ->where('organization_id', $this->organization($request)->id)
            ->when(
                isset($filters['status']),
                static fn (Builder $query) => $query->where('status', $filters['status']),
                static fn (Builder $query) => $query->where('status', '!=', VendorStatus::Archived),
            )
            ->when(isset($filters['q']) && trim((string) $filters['q']) !== '', static function (Builder $query) use ($filters): void {
                $term = '%'.addcslashes(trim((string) $filters['q']), '%_\\').'%';
                $query->where(static fn (Builder $q) => $q->whereLike('name', $term)->orWhereLike('name_en', $term)
                    ->orWhereLike('email', $term)->orWhereLike('cr_number', $term));
            })
            ->when(isset($filters['category_id']), static fn (Builder $query) => $query->whereHas(
                'categories', static fn (Builder $q) => $q->where('categories.public_id', strtolower((string) $filters['category_id'])),
            ))
            ->when(isset($filters['region_id']), static fn (Builder $query) => $query->whereHas(
                'region', static fn (Builder $q) => $q->where('public_id', strtolower((string) $filters['region_id'])),
            ))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($this->perPage($request, 20, 100));

        return $this->paginated($vendors, VendorResource::class);
    }

    public function store(StoreVendorRequest $request, CreateVendor $create): JsonResponse
    {
        $this->authorize('create', Vendor::class);

        $vendor = $create->handle($this->organization($request), $request->vendorData(), VendorSource::Web, CurrentActor::get());

        return $this->created(new VendorResource($vendor));
    }

    public function show(Vendor $vendor): JsonResponse
    {
        $this->authorize('view', $vendor);

        return $this->ok(new VendorResource($vendor));
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor, UpdateVendor $update): JsonResponse
    {
        $this->authorize('update', $vendor);

        return $this->ok(new VendorResource($update->handle($vendor, $request->vendorData(), CurrentActor::get())));
    }

    public function destroy(Vendor $vendor, ArchiveVendor $archive): JsonResponse
    {
        $this->authorize('delete', $vendor);

        return $this->ok(new VendorResource($archive->handle($vendor, CurrentActor::get())));
    }
}
