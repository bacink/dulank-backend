<?php

namespace App\Http\Resources\Admin;

use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Purchase
 */
class PurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'noPurchase' => $this->no_purchase,
            'date' => $this->date?->toDateString(),
            'created' => $this->created_at?->toIso8601String(),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier?->name),
            'product' => $this->whenLoaded('lines', fn () => $this->lines->first()?->name),
            'status' => $this->status,
            'amount' => (int) $this->amount,
            'paid' => (int) $this->paid,
            'due' => (int) $this->due,
            'paymentStatus' => $this->payment_status,
            'shippingCost' => (int) $this->shipping_cost,
            'tax' => (int) $this->tax,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'name' => $line->name,
                'qty' => (float) $line->qty,
                'unit' => $line->unit,
                'price' => (int) $line->price,
            ])->values()),
        ];
    }
}
