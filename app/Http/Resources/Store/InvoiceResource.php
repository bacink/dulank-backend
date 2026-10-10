<?php

namespace App\Http\Resources\Store;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Billing/invoice untuk audiens store (parity billings.json).
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->invoice_no,
            'userId' => (int) $this->user_id,
            'orderId' => $this->order?->number,
            'invoiceDate' => $this->invoice_date?->toDateString(),
            'dueDate' => $this->due_date?->toDateString(),
            'total' => (int) $this->amount,
            'status' => $this->status?->value,
        ];
    }
}
