<?php

namespace App\Http\Resources\Store;

use App\Models\OrderItem;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'productId' => $this->product_id !== null ? (int) $this->product_id : null,
            'name' => $this->name,
            'category' => $this->category,
            'quantity' => Num::quantity($this->quantity),
            'price' => (int) $this->price,
            'total' => (int) $this->total,
            'spec' => (string) $this->spec,
            'artwork' => $this->artwork,
            'printResult' => $this->print_result,
            'note' => $this->note,
        ];
    }
}
