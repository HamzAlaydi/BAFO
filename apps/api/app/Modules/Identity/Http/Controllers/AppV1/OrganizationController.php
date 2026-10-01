<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\AppV1;

use App\Modules\Identity\Actions\ReplaceOrganizationFile;
use App\Modules\Identity\Actions\UpdateOrganization;
use App\Modules\Identity\Http\Requests\UpdateOrganizationRequest;
use App\Modules\Identity\Http\Requests\UploadFileRequest;
use App\Modules\Identity\Http\Resources\OrganizationResource;
use App\Support\Auth\CurrentActor;
use App\Support\Files\FilePurpose;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * The user's own organization (API.md §1.3). Changes need `organization.update`, checked by the
 * route middleware `can:perm,'organization.update'` before validation.
 *
 *   GET /organization                                 Organization (own view)
 *   PATCH /organization                               Organization
 *   POST|DELETE /organization/logo                    Organization (image ≤ 2 MB, public)
 *   POST|DELETE /organization/profile-document        Organization (PDF ≤ 20 MB, private)
 */
final class OrganizationController extends IdentityController
{
    public function show(Request $request): JsonResponse
    {
        return $this->ok(new OrganizationResource(self::currentOrganization($request)));
    }

    public function update(UpdateOrganizationRequest $request, UpdateOrganization $update): JsonResponse
    {
        $organization = $update->handle(self::currentOrganization($request), $request->profileData(), CurrentActor::get());

        return $this->ok(new OrganizationResource($organization->refresh()));
    }

    public function storeLogo(UploadFileRequest $request, ReplaceOrganizationFile $replace): JsonResponse
    {
        return $this->replaceFile($request, $replace, FilePurpose::OrganizationLogo, $request->upload());
    }

    public function destroyLogo(Request $request, ReplaceOrganizationFile $replace): JsonResponse
    {
        return $this->replaceFile($request, $replace, FilePurpose::OrganizationLogo, null);
    }

    public function storeProfileDocument(UploadFileRequest $request, ReplaceOrganizationFile $replace): JsonResponse
    {
        return $this->replaceFile($request, $replace, FilePurpose::OrganizationProfile, $request->upload());
    }

    public function destroyProfileDocument(Request $request, ReplaceOrganizationFile $replace): JsonResponse
    {
        return $this->replaceFile($request, $replace, FilePurpose::OrganizationProfile, null);
    }

    private function replaceFile(Request $request, ReplaceOrganizationFile $replace, FilePurpose $purpose, ?UploadedFile $upload): JsonResponse
    {
        $organization = $replace->handle(self::currentOrganization($request), $purpose, $upload, CurrentActor::get());

        return $this->ok(new OrganizationResource($organization->refresh()));
    }
}
