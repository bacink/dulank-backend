<?php

namespace App\Enums;

/**
 * Metode pengiriman order.
 */
enum ShippingMethod: string
{
    case PickUp = 'Pick Up';
    case Shipping = 'Shipping';
    case Courier = 'Courier';
    case Express = 'Express';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * Label pengiriman versi admin (Pickup/Courier/Express/Shipping).
     */
    public function admin(): string
    {
        return match ($this) {
            self::PickUp => 'Pickup',
            default => $this->value,
        };
    }

    public static function fromAdmin(string $method): ?self
    {
        return match (strtolower(trim($method))) {
            'pickup', 'pick up' => self::PickUp,
            'courier' => self::Courier,
            'express' => self::Express,
            'shipping' => self::Shipping,
            default => null,
        };
    }
}
