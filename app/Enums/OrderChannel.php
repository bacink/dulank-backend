<?php

namespace App\Enums;

/**
 * Kanal asal order: Website (store), POS, atau hasil konversi Quotation.
 */
enum OrderChannel: string
{
    case Website = 'website';
    case Pos = 'pos';
    case Quotation = 'quotation';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Pos => 'POS',
            self::Quotation => 'Quotation',
        };
    }
}
