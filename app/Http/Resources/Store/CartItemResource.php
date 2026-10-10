<?php

namespace App\Http\Resources\Store;

use App\Models\CartItem;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'productId' => (int) $this->product_id,
            'name' => $this->name,
            'image' => $this->image,
            'quantity' => Num::quantity($this->quantity),
            'price' => (int) $this->price,
            'spec' => (string) $this->spec,
        ];
    }
}
