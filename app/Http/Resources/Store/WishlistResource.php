<?php

namespace App\Http\Resources\Store;

use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Wishlist
 */
class WishlistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => (int) $this->user_id,
            'productId' => (int) $this->product_id,
            'name' => $this->name,
            'image' => $this->image,
            'price' => (int) $this->price,
            'spec' => (string) $this->spec,
            'addedAt' => $this->added_at?->toDateString(),
            'expireDate' => $this->expire_date?->format('j/n/Y'),
            'expired' => $this->isExpired(),
        ];
    }
}
