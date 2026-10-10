<?php

namespace App\Http\Resources\Store;

use App\Models\PaperType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaperType
 */
class PaperTypeResource extends JsonResource
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
            'gsm' => $this->gsm !== null ? (float) $this->gsm : null,
            'updatedAt' => $this->updated_at?->toDateString(),
        ];
    }
}
