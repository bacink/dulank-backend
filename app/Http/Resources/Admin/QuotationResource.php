<?php

namespace App\Http\Resources\Admin;

use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quotation
 */
class QuotationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'noQuotation' => $this->no_quotation,
            'date' => $this->date?->toDateString(),
            'customer' => $this->customer_name,
            'email' => $this->customer_email,
            'status' => $this->status,
            'dateStatus' => $this->updated_at?->toDateString(),
            'total' => (int) $this->total,
            'channel' => $this->channel?->label(),
            'dueDate' => ($this->valid_until ?? $this->due_date)?->toDateString(),
            'document' => $this->document,
            'orderId' => $this->order_id !== null ? (string) $this->order_id : null,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'productName' => $item->product_name,
                'description' => $item->description,
                'moq' => $item->moq !== null ? (float) $item->moq : null,
                'unitPrice' => (int) $item->unit_price,
                'order' => (float) $item->order_qty,
                'unit' => $item->unit,
                'amount' => (int) $item->amount,
            ])->values()),
        ];
    }
}
