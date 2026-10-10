<?php

namespace App\Http\Resources\Store;

use App\Models\User;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Profil user store (parity users.json).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => (string) $this->phone,
            'avatar' => app(MediaService::class)->url($this->avatar_path),
            'joinDate' => $this->join_date?->toIso8601String(),
            'lastLogin' => $this->last_login_at?->toIso8601String(),
            'companyType' => $this->company_type,
            'companyName' => $this->company_name,
            'departmentName' => $this->department_name,
            'npwp' => $this->npwp,
            'transactionCode' => $this->transaction_code,
            'taxAddress' => $this->tax_address,
            'attachTaxInvoice' => (bool) $this->attach_tax_invoice,
            'twoFactorEnabled' => $this->hasEnabledTwoFactor(),
        ];
    }
}
