<?php

namespace App\Http\Resources\Admin;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $contact = (array) ($this->order?->contact ?? []);

        return [
            'id' => (string) $this->id,
            'invoiceNo' => $this->invoice_no,
            'orderId' => $this->order?->number,
            'customer' => $contact['name'] ?? $this->user?->name,
            'invoiceDate' => $this->invoice_date?->toDateString(),
            'dueDate' => $this->due_date?->toDateString(),
            'amount' => (int) $this->amount,
            'paid' => (int) $this->paid,
            'amountDue' => (int) $this->amount_due,
            'status' => $this->status?->value,
            'created' => $this->created_at?->toIso8601String(),
        ];
    }
}
