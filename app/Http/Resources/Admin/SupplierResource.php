<?php

namespace App\Http\Resources\Admin;

use App\Models\Supplier;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Supplier
 */
class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'supplierId' => $this->code,
            'name' => $this->name,
            'photo' => app(MediaService::class)->url($this->photo_path),
            'email' => $this->email,
            'contact' => $this->contact,
            'picName' => $this->pic_name,
            'status' => $this->status,
            'date' => $this->date?->toDateString(),
        ];
    }
}
