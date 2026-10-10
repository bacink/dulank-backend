<?php

namespace App\Http\Resources\Store;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
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
            'description' => (string) $this->description,
            'image' => $this->image_path,
            'productCount' => (int) ($this->products_count ?? 0),
        ];
    }
}
