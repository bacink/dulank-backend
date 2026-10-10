<?php

namespace App\Http\Resources\Store;

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
            'id' => $this->no_quotation,
            'userId' => (int) $this->user_id,
            'date' => $this->date?->toDateString(),
            'validUntil' => $this->valid_until?->toDateString(),
            'status' => $this->status,
            'items' => QuotationItemResource::collection($this->whenLoaded('items')),
            'customerInfo' => [
                'name' => (string) $this->customer_name,
                'email' => (string) $this->customer_email,
                'phone' => (string) $this->customer_phone,
            ],
            'subtotal' => (int) $this->sub_total,
            'tax' => (int) $this->tax,
            'shippingCost' => (int) $this->shipping_cost,
            'total' => (int) $this->total,
        ];
    }
}
