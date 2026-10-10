<?php

namespace App\Enums;

/**
 * Status pembayaran dokumen (order/purchase/sales return).
 */
enum PaymentStatus: string
{
    case Paid = 'Paid';
    case Partial = 'Partial';
    case Unpaid = 'Unpaid';
    case Refunded = 'Refunded';
    case Cancelled = 'Cancelled';

    public function label(): string
    {
        return $this->value;
    }
}
