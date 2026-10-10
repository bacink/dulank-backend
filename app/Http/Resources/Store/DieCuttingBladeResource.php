<?php

namespace App\Http\Resources\Store;

use App\Models\DieCuttingBlade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DieCuttingBlade
 */
class DieCuttingBladeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'pricePerCm' => (int) $this->price_per_cm,
            'updatedAt' => $this->updated_at?->toDateString(),
            'published' => (bool) $this->published,
            'active' => (bool) $this->active,
        ];
    }
}
