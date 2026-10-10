<?php

namespace App\Http\Resources\Admin;

use App\Models\BankLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankLedgerEntry
 */
class BankLedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'bankAccountId' => (string) $this->bank_account_id,
            'branchId' => $this->branch_id !== null ? (string) $this->branch_id : null,
            'direction' => $this->direction,
            'amount' => (int) $this->amount,
            'occurredAt' => $this->occurred_at?->toIso8601String(),
            'referenceType' => $this->reference_type,
            'referenceId' => $this->reference_id !== null ? (string) $this->reference_id : null,
            'description' => $this->description,
            'created' => $this->created_at?->toIso8601String(),
        ];
    }
}
