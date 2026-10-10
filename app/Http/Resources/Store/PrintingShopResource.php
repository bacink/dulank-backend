<?php

namespace App\Http\Resources\Store;

use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Partner
 */
class PrintingShopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'province' => $this->province,
            'city' => $this->city,
            'district' => $this->district,
            'followers' => $this->followers_label ?? (string) $this->followers_count,
            'following' => (int) $this->following_count,
            'paperCount' => (int) ($this->metrics?->paper_count ?? 0),
            'printingCount' => (int) ($this->metrics?->printing_count ?? 0),
            'laminationCount' => (int) ($this->metrics?->lamination_count ?? 0),
            'dieCuttingCount' => (int) ($this->metrics?->die_cutting_count ?? 0),
            'foilCount' => (int) ($this->metrics?->foil_count ?? 0),
            'image' => $this->image,
        ];
    }
}
