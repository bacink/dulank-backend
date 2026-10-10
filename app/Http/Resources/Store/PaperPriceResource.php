<?php

namespace App\Http\Resources\Store;

use App\Models\PaperPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaperPrice
 */
class PaperPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'name' => $this->name,
            'group' => $this->group?->name,
            'price' => (int) $this->price,
            'updatedAt' => $this->updated_at?->toDateString(),
            'published' => (bool) $this->published,
            'active' => (bool) $this->active,
        ];
    }
}
