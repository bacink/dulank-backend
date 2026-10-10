<?php

namespace App\Enums;

/**
 * Tipe harga produk pada katalog admin.
 */
enum PriceType: string
{
    case Fixed = 'Fixed';
    case Range = 'Range';
    case Calculator = 'Calculator';

    public function label(): string
    {
        return $this->value;
    }
}
