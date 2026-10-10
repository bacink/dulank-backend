<?php

namespace App\Http\Resources\Store;

use App\Models\PaperGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaperGroup
 */
class PaperGroupResource extends JsonResource
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
            'brand' => $this->merk,
            'updatedAt' => $this->updated_at?->toDateString(),
        ];
    }
}
