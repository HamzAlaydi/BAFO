<?php

declare(strict_types=1);

namespace App\Modules\Billing\Events;

use App\Modules\Billing\Models\Coupon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The admin issued a voucher for unused passes (ARCHITECTURE §10, §13.5). Listener: Notifications `voucher.issued`.
 */
final readonly class VoucherIssued
{
    use Dispatchable, SerializesModels;

    public function __construct(public Coupon $voucher) {}
}
