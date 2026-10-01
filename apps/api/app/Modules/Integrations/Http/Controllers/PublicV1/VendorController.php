<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers\PublicV1;

use App\Modules\Integrations\Actions\Vendors\CreateVendor;
use App\Modules\Integrations\Actions\Vendors\UpdateVendor;
use App\Modules\Integrations\Actions\Vendors\UpsertVendorByExternalRef;
use App\Modules\Integrations\Enums\VendorSource;
use App\Modules\Integrations\Enums\VendorStatus;
use App\Modules\Integrations\Http\Concerns\ResolvesTenant;
use App\Modules\Integrations\Http\Requests\StoreVendorRequest;
use App\Modules\Integrations\Http\Requests\UpdateVendorRequest;
use App\Modules\Integrations\Http\Requests\UpsertVendorRequest;
use App\Modules\Integrations\Http\Resources\VendorResource;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Services\ExternalRefs;
use App\Support\Auth\CurrentActor;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public vendors, flow F1 (API.md §3.3): the API client's organization only (§8.6).
 */
final class VendorController extends ApiController
{
    use ResolvesTenant;

    /**
     * Filters: `status`, `q`, `updated_since`, `external_system` + `external_id`, `linked`.
     * Cursor pagination ordered by `updated_at, id` (API.md §0.5).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::enum(VendorStatus::class)],
            'q' => ['nullable', 'string', 'max:200'],
            'external_system' => ['nullable', 'required_with:external_id', 'string', 'max:60', 'regex:'.ExternalRef::SYSTEM_PATTERN],
            'external_id' => ['nullable', 'required_with:external_system', 'string', 'max:120'],
            'linked' => ['nullable', 'boolean'],
        ]);
        $updatedSince = $this->updatedSince($request);
        $organizationId = $this->organization($request)->id;

        $vendors = Vendor::query()
            ->with(VendorResource::RELATIONS)
            ->where('organization_id', $organizationId)
            ->when(isset($filters['status']), static fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($updatedSince !== null, static fn (Builder $query) => $query->where('updated_at', '>=', $updatedSince))
            ->when(isset($filters['q']) && trim((string) $filters['q']) !== '', static function (Builder $query) use ($filters): void {
                $term = '%'.addcslashes(trim((string) $filters['q']), '%_\\').'%';
                $query->where(static fn (Builder $q) => $q->whereLike('name', $term)->orWhereLike('name_en', $term)
                    ->orWhereLike('email', $term)->orWhereLike('cr_number', $term));
            })
            ->when(isset($filters['external_system']), static fn (Builder $query) => $query->whereHas(
                'externalRefs',
                static fn (Builder $q) => $q->where('system', $filters['external_system'])->where('value', $filters['external_id']),
            ))
            ->when(isset($filters['linked']), static fn (Builder $query) => $request->boolean('linked')
                ? $query->whereNotNull('linked_organization_id')
                : $query->whereNull('linked_organization_id'))
            ->orderBy('updated_at')
            ->orderBy('id')
            ->cursorPaginate($this->perPage($request, 50, 200));

        return $this->paginated($vendors, VendorResource::class);
    }

    public function store(StoreVendorRequest $request, CreateVendor $create): JsonResponse
    {
        $vendor = $create->handle($this->organization($request), $request->vendorData(), VendorSource::Api, CurrentActor::get());

        return $this->created(new VendorResource($vendor))->header('Location', self::location($vendor));
    }

    public function show(Request $request, Vendor $vendor): JsonResponse
    {
        $this->ensureOwnedByClient($request, $vendor->organization_id);

        return $this->ok(new VendorResource($vendor));
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor, UpdateVendor $update): JsonResponse
    {
        $this->ensureOwnedByClient($request, $vendor->organization_id);

        return $this->ok(new VendorResource($update->handle($vendor, $request->vendorData(), CurrentActor::get())));
    }

    public function upsert(UpsertVendorRequest $request, UpsertVendorByExternalRef $upsert): JsonResponse
    {
        [$vendor, $created] = $upsert->handle(
            $this->organization($request),
            $request->system(),
            $request->externalId(),
            $request->vendorData(),
            CurrentActor::get(),
        );

        return $created
            ? $this->created(new VendorResource($vendor))->header('Location', self::location($vendor))
            : $this->ok(new VendorResource($vendor));
    }

    public function showByExternal(Request $request, ExternalRefs $externalRefs): JsonResponse
    {
        $vendorId = $externalRefs->findRefableId(
            $this->organization($request)->id,
            'vendor',
            (string) $request->route('system'),
            ExternalRefs::TYPE_SUPPLIER,
            trim((string) $request->route('external_id')),
        );

        if ($vendorId === null) {
            throw new ModelNotFoundException;
        }

        return $this->ok(new VendorResource(Vendor::query()->findOrFail($vendorId)));
    }

    private static function location(Vendor $vendor): string
    {
        return url('/api/public/v1/vendors/'.$vendor->public_id);
    }
}
