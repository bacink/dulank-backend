<?php

namespace App\Http\Resources\Admin;

use App\Models\MoneyTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MoneyTransfer
 */
class MoneyTransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'no' => $this->no,
            'branchId' => $this->branch_id !== null ? (string) $this->branch_id : null,
            'fromAccountId' => (string) $this->from_account_id,
            'toAccountId' => (string) $this->to_account_id,
            'amount' => (int) $this->amount,
            'description' => $this->description,
            'occurredAt' => $this->occurred_at?->toIso8601String(),
            'createdById' => $this->created_by !== null ? (string) $this->created_by : null,
            'createdBy' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'date' => $this->occurred_at?->toDateString(),
            'fromAccount' => $this->whenLoaded('fromAccount', fn () => $this->fromAccount?->account_name),
            'toAccount' => $this->whenLoaded('toAccount', fn () => $this->toAccount?->account_name),
        ];
    }
}
