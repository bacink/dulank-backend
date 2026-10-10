<?php

namespace App\Http\Resources\Admin;

use App\Models\BankAccount;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankAccount
 */
class BankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'branchId' => $this->branch_id !== null ? (string) $this->branch_id : null,
            'accountTypeId' => $this->bank_account_type_id !== null ? (string) $this->bank_account_type_id : null,
            'accountName' => $this->account_name,
            'bankName' => $this->bank_name,
            'accountNo' => $this->account_no,
            'openingBalance' => (int) $this->opening_balance,
            'ifsc' => $this->ifsc,
            'description' => $this->description,
            'status' => $this->status,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'accountTypeName' => $this->whenLoaded('accountType', fn () => $this->accountType?->name),
            'branchName' => $this->whenLoaded('branch', fn () => $this->branch?->store_name),
            'currentBalance' => app(LedgerService::class)->balance($this->resource),
        ];
    }
}
