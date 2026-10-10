<?php

namespace App\Http\Resources\Store;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Order untuk audiens store (parity dengan server/data/orders.json).
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
        return [
            'id' => $this->number,
            'userId' => (int) $this->user_id,
            'status' => $this->status?->legacy(),
            'date' => ($this->ordered_at ?? $this->created_at)?->toDateString(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'shippingAddress' => $this->shipping_address ?? [],
            'total' => (int) $this->total,
            'timeline' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($history) => [
                'status' => $history->status,
                'date' => $history->occurred_at !== null
                    ? $history->occurred_at->format('Y-m-d H:i').' WIB'
                    : null,
            ])->values()),
        ];
    }
}
