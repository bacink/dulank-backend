<?php

namespace App\Http\Resources\Store;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sale banner store (dipetakan dari tabel banners tipe `product`).
 *
 * @mixin Banner
 */
class SaleBannerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'discount' => (string) $this->discount,
            'image' => $this->image_url,
        ];
    }
}
