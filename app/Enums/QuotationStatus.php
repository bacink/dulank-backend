<?php

namespace App\Enums;

/**
 * Status quotation (PRD §4.3). Untuk Fase 1 kolom quotations.status
 * disimpan sebagai string apa adanya (data impor legacy); enum ini dipakai
 * untuk validasi & transisi di Fase 2+.
 */
enum QuotationStatus: string
{
    case Draft = 'Draft';
    case Pending = 'Pending';
    case Send = 'Send';
    case Ordered = 'Ordered';
    case Rejected = 'Rejected';
    case Complete = 'Complete';

    public function label(): string
    {
        return $this->value;
    }
}
