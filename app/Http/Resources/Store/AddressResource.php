<?php

namespace App\Http\Resources\Store;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Address
 */
class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => (int) $this->addressable_id,
            'recipientName' => $this->recipient_name,
            'phone' => (string) $this->phone,
            'type' => $this->type,
            'name' => $this->name,
            'street' => $this->street,
            'district' => $this->district,
            'city' => $this->city,
            'province' => $this->province,
            'postalCode' => $this->postal_code,
            'country' => $this->country,
            'isDefault' => (bool) $this->is_default,
        ];
    }
}
