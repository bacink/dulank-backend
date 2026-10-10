<?php

namespace App\Enums;

/**
 * Tipe banner: `main` (hero) atau `product` (sale banner store).
 */
enum BannerType: string
{
    case Main = 'main';
    case Product = 'product';

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Main Banner',
            self::Product => 'Product Banner',
        };
    }
}
