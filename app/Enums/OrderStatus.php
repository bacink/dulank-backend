<?php

namespace App\Enums;

/**
 * Siklus hidup order (PRD §7.3).
 *
 * Nilai database memakai snake_case; resource store mengembalikan string
 * legacy kebab-case agar kontrak frontend lama tidak berubah.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case AwaitingPayment = 'awaiting_payment';
    case Design = 'design';
    case Production = 'production';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * Status versi mock store (kebab-case).
     */
    public function legacy(): string
    {
        return match ($this) {
            self::AwaitingConfirmation => 'awaiting-confirmation',
            self::AwaitingPayment => 'awaiting-payment',
            default => $this->value,
        };
    }

    public static function fromLegacy(string $status): self
    {
        return match ($status) {
            'awaiting-confirmation' => self::AwaitingConfirmation,
            'awaiting-payment' => self::AwaitingPayment,
            'ordered', 'pending' => self::Pending,
            default => self::from($status),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::AwaitingConfirmation => 'Awaiting Confirmation',
            self::AwaitingPayment => 'Awaiting Payment',
            self::Design => 'Design',
            self::Production => 'Production',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Label status versi admin (Complete/Waiting/Processing/Cancel).
     */
    public function admin(): string
    {
        return match ($this) {
            self::Pending, self::AwaitingConfirmation => 'Waiting',
            self::AwaitingPayment, self::Design, self::Production, self::Shipped => 'Processing',
            self::Delivered => 'Complete',
            self::Cancelled => 'Cancel',
        };
    }

    /**
     * Terima label admin maupun status legacy/canonical.
     */
    public static function fromAdmin(string $status): self
    {
        return match (strtolower(trim($status))) {
            'waiting' => self::AwaitingConfirmation,
            'processing' => self::Production,
            'complete' => self::Delivered,
            'cancel', 'cancelled' => self::Cancelled,
            default => self::fromLegacy($status),
        };
    }
}
