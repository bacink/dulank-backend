<?php

namespace App\Enums;

/**
 * Status tiket dukungan. Resource store mengembalikan huruf kecil
 * (open/pending/resolved/closed) sesuai kontrak mock lama.
 */
enum TicketStatus: string
{
    case Open = 'Open';
    case Pending = 'Pending';
    case Resolved = 'Resolved';
    case Closed = 'Closed';

    public function legacy(): string
    {
        return strtolower($this->value);
    }

    public static function fromLegacy(string $status): self
    {
        return match (strtolower($status)) {
            'open' => self::Open,
            'pending' => self::Pending,
            'resolved' => self::Resolved,
            'closed' => self::Closed,
            default => self::Open,
        };
    }

    public function label(): string
    {
        return $this->value;
    }
}
