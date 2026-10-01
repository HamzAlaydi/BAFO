<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\WebhookEndpoints\Pages;

use App\Modules\Admin\Filament\Resources\WebhookEndpoints\WebhookEndpointResource;
use Filament\Resources\Pages\ListRecords;

final class ListWebhookEndpoints extends ListRecords
{
    protected static string $resource = WebhookEndpointResource::class;
}
