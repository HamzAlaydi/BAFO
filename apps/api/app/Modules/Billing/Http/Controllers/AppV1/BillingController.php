<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\AppV1;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Base of the Billing app v1 controllers: the signed-in user and their organization (v1: one
 * membership per user).
 */
abstract class BillingController extends ApiController
{
    protected function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    protected function organization(User $user): Organization
    {
        $membership = $user->membership;

        if ($membership === null) {
            throw new NotFoundHttpException;
        }

        return $membership->organization;
    }
}
