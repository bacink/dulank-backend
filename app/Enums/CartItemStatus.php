<?php

namespace App\Enums;

/**
 * Status item keranjang: Active, Checkout (dipindah ke order), Deleted.
 */
enum CartItemStatus: string
{
    case Active = 'Active';
    case Checkout = 'Checkout';
    case Deleted = 'Deleted';

    public function label(): string
    {
        return $this->value;
    }
}
