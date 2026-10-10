<?php

namespace App\Http\Resources\Store;

use App\Models\Machine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Machine
 */
class MachineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'source' => $this->source,
            'name' => $this->name,
            'size' => $this->size,
            'price' => $this->price !== null ? (int) $this->price : null,
            'unitPrice' => $this->unit_price !== null ? (int) $this->unit_price : null,
            'updatedAt' => $this->updated_at?->toDateString(),
            'published' => (bool) $this->published,
            'active' => (bool) $this->active,
        ];
    }
}
