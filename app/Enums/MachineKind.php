<?php

namespace App\Enums;

/**
 * Jenis mesin pada katalog marketplace.
 *
 * Nilai backing memakai string kebab-case yang sama dengan kontrak
 * mock store (printing/lamination/die-cutting/foil).
 */
enum MachineKind: string
{
    case Printing = 'printing';
    case Lamination = 'lamination';
    case DieCutting = 'die-cutting';
    case Foil = 'foil';

    public function label(): string
    {
        return match ($this) {
            self::Printing => 'Printing',
            self::Lamination => 'Lamination',
            self::DieCutting => 'Die Cutting',
            self::Foil => 'Foil',
        };
    }
}
