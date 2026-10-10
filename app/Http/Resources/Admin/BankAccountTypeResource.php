<?php

namespace App\Http\Resources\Admin;

use App\Models\BankAccountType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankAccountType
 */
class BankAccountTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'createdDate' => $this->created_at?->toDateString(),
            'accountCount' => (int) ($this->accounts_count ?? 0),
        ];
    }
}
