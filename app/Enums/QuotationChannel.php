<?php

namespace App\Enums;

/**
 * Kanal quotation: Online (store), Sales Staff, Offline.
 */
enum QuotationChannel: string
{
    case Online = 'online';
    case SalesStaff = 'sales_staff';
    case Offline = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::SalesStaff => 'Sales Staff',
            self::Offline => 'Offline',
        };
    }

    /**
     * Terima label channel versi admin/store.
     */
    public static function fromAdmin(string $channel): self
    {
        return match (strtolower(str_replace(' ', '_', trim($channel)))) {
            'sales_staff' => self::SalesStaff,
            'offline' => self::Offline,
            default => self::Online,
        };
    }
}
