<?php

namespace App\Http\Resources\Admin;

use App\Models\PurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseItem
 */
class PurchaseItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'product' => $this->name,
            'description' => $this->description,
            'merk' => $this->merk,
            'price' => (int) $this->price,
            'unit' => $this->unit,
            'created' => $this->created_at?->toIso8601String(),
        ];
    }
}
