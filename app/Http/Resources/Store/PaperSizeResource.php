<?php

namespace App\Http\Resources\Store;

use App\Models\PaperSize;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaperSize
 */
class PaperSizeResource extends JsonResource
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
            'width' => $this->width !== null ? (float) $this->width : null,
            'height' => $this->length !== null ? (float) $this->length : null,
            'unit' => $this->unit,
            'updatedAt' => $this->updated_at?->toDateString(),
        ];
    }
}
