<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\AppV1;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

/**
 * Base of the Identity app v1 controllers: the authenticated Identity user and their
 * organization.
 */
abstract class IdentityController extends ApiController
{
    protected static function currentUser(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException;
    }

    protected static function currentOrganization(Request $request): Organization
    {
        $user = self::currentUser($request);
        $user->loadMissing('membership.organization');

        return $user->membership->organization ?? throw new AuthenticationException;
    }
}
