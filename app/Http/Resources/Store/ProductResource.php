<?php

namespace App\Http\Resources\Store;

use App\Enums\PriceType;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk produk untuk audiens store (parity dengan server/data/products.json).
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'categoryId' => $this->category_id !== null ? (int) $this->category_id : null,
            'description' => (string) $this->description,
            'priceFrom' => (int) ($this->price_from ?? $this->price),
            'priceTo' => (int) ($this->price_to ?? $this->price),
            'image' => $this->image_path,
            'specs' => $this->whenLoaded('specs', fn () => $this->specs->map(fn ($spec) => [
                'group' => $spec->group_name,
                'options' => $spec->options ?? [],
            ])->values()),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->values()),
            'isCalculator' => $this->price_type === PriceType::Calculator,
        ];
    }
}
