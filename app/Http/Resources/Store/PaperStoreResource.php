<?php

namespace App\Http\Resources\Store;

use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Partner
 */
class PaperStoreResource extends JsonResource
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
            'groupCount' => (int) ($this->metrics?->group_count ?? 0),
            'typeCount' => (int) ($this->metrics?->type_count ?? 0),
            'image' => $this->image,
        ];
    }
}
