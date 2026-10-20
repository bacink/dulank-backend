<?php

namespace App\Support;

use App\Enums\PriceType;
use App\Enums\ProductType;

final class ProductPricingContract
{
    public static function defaultPriceType(ProductType $type): PriceType
    {
        return match ($type) {
            ProductType::Single => PriceType::Fixed,
            ProductType::Variable => PriceType::Range,
            default => PriceType::Calculator,
        };
    }
}
