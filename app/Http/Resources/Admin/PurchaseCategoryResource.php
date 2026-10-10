<?php

namespace App\Http\Resources\Admin;

use App\Models\PurchaseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseCategory
 */
class PurchaseCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'created' => $this->created_at?->toDateString(),
            'status' => $this->status,
            'itemCount' => (int) ($this->items_count ?? 0),
        ];
    }
}
