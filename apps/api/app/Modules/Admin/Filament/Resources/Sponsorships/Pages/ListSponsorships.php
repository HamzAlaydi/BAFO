<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Sponsorships\Pages;

use App\Modules\Admin\Filament\Resources\Sponsorships\SponsorshipResource;
use Filament\Resources\Pages\ListRecords;

final class ListSponsorships extends ListRecords
{
    protected static string $resource = SponsorshipResource::class;
}
