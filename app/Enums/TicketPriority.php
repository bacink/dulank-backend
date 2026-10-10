<?php

namespace App\Enums;

/**
 * Prioritas tiket. Resource store mengembalikan huruf kecil.
 */
enum TicketPriority: string
{
    case High = 'High';
    case Medium = 'Medium';
    case Low = 'Low';

    public function legacy(): string
    {
        return strtolower($this->value);
    }

    public static function fromLegacy(string $priority): self
    {
        return match (strtolower($priority)) {
            'high' => self::High,
            'low' => self::Low,
            default => self::Medium,
        };
    }

    public function label(): string
    {
        return $this->value;
    }
}
