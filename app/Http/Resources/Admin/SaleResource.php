<?php

namespace App\Http\Resources\Admin;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sale/POS admin (order sebagai dokumen penjualan).
 *
 * @mixin Order
 */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $contact = (array) ($this->contact ?? []);

        return [
            'id' => (string) $this->id,
            'saleNo' => $this->number,
            'customer' => $contact['name'] ?? $this->user?->name,
            'date' => ($this->ordered_at ?? $this->created_at)?->toDateString(),
            'subTotal' => (int) $this->sub_total,
            'deliveryFee' => (int) $this->delivery_fee,
            'discount' => (int) $this->discount,
            'tax' => (int) $this->tax,
            'total' => (int) $this->total,
            'delivery' => $this->shipping_method?->admin() ?? 'Pickup',
            'channel' => $this->channel?->label(),
            'status' => $this->payment_status?->value ?? 'Unpaid',
            'method' => $this->whenLoaded('payments', fn () => $this->payments
                ->sortByDesc('id')
                ->first()?->method),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'productId' => $item->product_id !== null ? (string) $item->product_id : null,
                'name' => $item->name,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
                'price' => (int) $item->price,
                'total' => (int) $item->total,
                'spec' => (string) $item->spec,
            ])->values()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => [
                'id' => (string) $payment->id,
                'amount' => (int) $payment->amount,
                'date' => $payment->date?->toDateString(),
                'method' => $payment->method,
                'status' => $payment->status?->value,
            ])->values()),
            'canonicalStatus' => $this->status?->legacy(),
            'paid' => (int) $this->paid,
            'due' => (int) $this->due,
        ];
    }
}
