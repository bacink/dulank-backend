<?php

namespace App\Enums;

/**
 * Status listing katalog marketplace (moderasi admin).
 */
enum ListingStatus: string
{
    case Active = 'Active';
    case Inactive = 'Inactive';
    case Frozen = 'Frozen';

    public function label(): string
    {
        return $this->value;
    }
}
