<?php

declare(strict_types=1);

arch('application code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging helpers are left behind')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('domain modules do not depend on the admin panel')
    ->expect([
        'App\\Modules\\Identity',
        'App\\Modules\\Catalog',
        'App\\Modules\\Competitions',
        'App\\Modules\\Bidding',
        'App\\Modules\\Billing',
        'App\\Modules\\Notifications',
        'App\\Modules\\Integrations',
        'App\\Modules\\Platform',
    ])
    ->not->toUse('App\\Modules\\Admin');
