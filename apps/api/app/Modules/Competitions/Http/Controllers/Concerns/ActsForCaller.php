<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Controllers\Concerns;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Auth\Actor;
use App\Support\Auth\CurrentActor;
use Illuminate\Http\Request;

/**
 * The caller of an app v1 request: its actor, user and organization.
 */
trait ActsForCaller
{
    protected function actor(): Actor
    {
        return CurrentActor::get();
    }

    protected function user(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    protected function organization(): Organization
    {
        return Organization::query()->findOrFail(CurrentActor::get()->organizationId);
    }
}
