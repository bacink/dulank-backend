<?php

namespace App\Enums;

/**
 * Status produk (master admin). Produk yang diarsipkan memakai archived_at.
 */
enum ProductStatus: string
{
    case Active = 'Active';
    case Inactive = 'Inactive';

    public function label(): string
    {
        return $this->value;
    }
}
