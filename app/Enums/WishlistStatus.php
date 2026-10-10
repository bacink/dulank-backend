<?php

namespace App\Enums;

/**
 * Status entri wishlist.
 */
enum WishlistStatus: string
{
    case Active = 'Active';
    case Expired = 'Expired';
    case Checkout = 'Checkout';

    public function label(): string
    {
        return $this->value;
    }
}
