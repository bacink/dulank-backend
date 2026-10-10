<?php

namespace App\Enums;

/**
 * Status verifikasi pembayaran yang dikirim pelanggan.
 *
 * Hanya pembayaran berstatus Verified yang dihitung ke `paid` invoice
 * (PRD §7.4); admin memverifikasi pada Fase 3.
 */
enum PaymentVerification: string
{
    case Pending = 'Pending';
    case Verified = 'Verified';
    case Rejected = 'Rejected';

    public function label(): string
    {
        return $this->value;
    }
}
