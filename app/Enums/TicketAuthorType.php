<?php

namespace App\Enums;

/**
 * Penulis pesan tiket: pelanggan (store) atau tim support (admin).
 */
enum TicketAuthorType: string
{
    case User = 'user';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Support => 'Support',
        };
    }
}
