<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\PublicV1\OrganizationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Identity module: public ERP API (/api/public/v1), API.md §3.2
|--------------------------------------------------------------------------
|
| Loaded by App\Support\Routing\ApiRoutes with middleware group "public_v1"
| (JSON, Accept-Language; Integrations appends API client auth, throttle:public-api
| and bindings) and route names "public.v1.*". Every route declares its scope with
| ->middleware('api.scope:<scope>').
|
*/

Route::get('organization', OrganizationController::class)
    ->middleware('api.scope:organization:read')
    ->name('organization');
