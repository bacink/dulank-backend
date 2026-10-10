<?php

namespace App\Enums;

/**
 * Kepemilikan data katalog (paper/machine): milik sendiri atau vendor.
 */
enum PaperOwnerType: string
{
    case Self = 'self';
    case Vendor = 'vendor';

    public function label(): string
    {
        return match ($this) {
            self::Self => 'Milik Sendiri',
            self::Vendor => 'Vendor',
        };
    }
}
