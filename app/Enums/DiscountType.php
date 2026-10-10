<?php

namespace App\Enums;

/**
 * Tipe potongan harga (coupon/discount/voucher).
 */
enum DiscountType: string
{
    case Fixed = 'Fixed';
    case Percentage = 'Percentage';

    public function label(): string
    {
        return $this->value;
    }
}
