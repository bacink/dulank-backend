<?php

namespace App\Enums;

/**
 * Status invoice/billing.
 */
enum InvoiceStatus: string
{
    case Paid = 'Paid';
    case Partial = 'Partial';
    case Unpaid = 'Unpaid';
    case Cancelled = 'Cancelled';

    public function label(): string
    {
        return $this->value;
    }
}
