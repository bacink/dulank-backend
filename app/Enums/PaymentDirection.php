<?php

namespace App\Enums;

/**
 * Arah pembayaran: masuk (dari customer) atau keluar (ke supplier).
 */
enum PaymentDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Inflow',
            self::Out => 'Outflow',
        };
    }
}
