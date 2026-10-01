<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Subscriptions\Pages;

use App\Modules\Admin\Filament\Resources\Subscriptions\SubscriptionResource;
use Filament\Resources\Pages\ListRecords;

final class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;
}
