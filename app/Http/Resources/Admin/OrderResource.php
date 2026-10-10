<?php

namespace App\Http\Resources\Admin;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Order monitoring admin.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $contact = (array) ($this->contact ?? []);

        return [
            'id' => (string) $this->id,
            'no' => $this->number,
            'customerId' => $this->customer_id !== null ? (string) $this->customer_id : null,
            'branchId' => $this->branch_id !== null ? (string) $this->branch_id : null,
            'customer' => $contact['name'] ?? $this->user?->name,
            'orderDate' => ($this->ordered_at ?? $this->created_at)?->toDateString(),
            'status' => $this->status?->admin(),
            'statusBy' => $this->status_by,
            'salesChannel' => $this->channel?->label(),
            'shipping' => $this->shipping_method?->admin(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'productId' => $item->product_id !== null ? (string) $item->product_id : null,
                'productName' => $item->name,
                'qty' => (float) $item->quantity,
                'unitPrice' => (int) $item->price,
                'total' => (int) $item->total,
            ])->values()),
            'totalAmount' => (int) $this->total,
            'canonicalStatus' => $this->status?->legacy(),
            'paymentStatus' => $this->payment_status?->value,
            'paid' => (int) $this->paid,
            'due' => (int) $this->due,
            'created' => $this->created_at?->toIso8601String(),
        ];
    }
}
