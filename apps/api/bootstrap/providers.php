<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;

// Module providers (app/Modules/*/*ServiceProvider.php) are registered by AppServiceProvider.
return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    AdminPanelProvider::class,
];
