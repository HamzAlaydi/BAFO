<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Coupons\Pages;

use App\Modules\Admin\Filament\Resources\Coupons\CouponResource;
use App\Modules\Admin\Filament\Support\EditsReferenceRecords;
use Filament\Resources\Pages\EditRecord;

final class EditCoupon extends EditRecord
{
    use EditsReferenceRecords;

    protected static string $resource = CouponResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CouponResource::normalize($data);
    }
}
