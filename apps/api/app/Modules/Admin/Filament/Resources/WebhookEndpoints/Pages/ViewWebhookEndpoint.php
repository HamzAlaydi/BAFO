<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\WebhookEndpoints\Pages;

use App\Modules\Admin\Filament\Resources\WebhookEndpoints\WebhookEndpointResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewWebhookEndpoint extends ViewRecord
{
    protected static string $resource = WebhookEndpointResource::class;
}
