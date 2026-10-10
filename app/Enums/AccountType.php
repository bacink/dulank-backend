<?php

namespace App\Enums;

/**
 * Tipe akun: pemilik token `store` atau `admin` (ability Sanctum).
 */
enum AccountType: string
{
    case Store = 'store';
    case Admin = 'admin';

    public function ability(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Store => 'Store Customer',
            self::Admin => 'Administrator',
        };
    }
}
