<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers\AppV1;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Actions\RegisterDevice;
use App\Modules\Notifications\Actions\RemoveDevice;
use App\Modules\Notifications\Http\Requests\StoreDeviceRequest;
use App\Modules\Notifications\Http\Resources\DeviceResource;
use App\Modules\Notifications\Models\DeviceToken;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Push registrations of the signed-in user (API.md §1.8).
 */
final class DeviceController extends ApiController
{
    /** `POST /devices`: 201 `Device`, upsert by token. */
    public function store(StoreDeviceRequest $request, RegisterDevice $action): JsonResponse
    {
        return $this->created(DeviceResource::make($action->handle(self::user($request), $request->registration())));
    }

    /** `DELETE /devices/{device}` (the owner only). */
    public function destroy(Request $request, DeviceToken $device, RemoveDevice $action): JsonResponse
    {
        $this->authorize('delete', $device);

        $action->handle(self::user($request), $device);

        return $this->noContent();
    }

    private static function user(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }
}
