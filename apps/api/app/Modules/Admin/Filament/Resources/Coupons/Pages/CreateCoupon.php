<?php

declare(strict_types=1);

namespace App\Modules\Admin\Filament\Resources\Coupons\Pages;

use App\Modules\Admin\Filament\Resources\Coupons\CouponResource;
use App\Modules\Admin\Filament\Support\CreatesReferenceRecords;
use App\Modules\Admin\Support\AdminActor;
use App\Modules\Billing\Enums\CouponKind;
use Filament\Resources\Pages\CreateRecord;

final class CreateCoupon extends CreateRecord
{
    use CreatesReferenceRecords;

    protected static string $resource = CouponResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [
            ...CouponResource::normalize($data),
            'kind' => CouponKind::Coupon,
            'created_by_admin_id' => AdminActor::admin()->id,
        ];
    }
}
