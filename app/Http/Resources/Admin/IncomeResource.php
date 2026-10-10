<?php

namespace App\Http\Resources\Admin;

use App\Models\Income;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Income
 */
class IncomeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'date' => $this->date?->toDateString(),
            'no' => $this->no,
            'name' => $this->name,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'notes' => $this->notes,
            'amount' => (int) $this->amount,
            'paymentMethod' => $this->payment_method,
            'bankAccountId' => $this->bank_account_id !== null ? (string) $this->bank_account_id : null,
            'bankAccount' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount?->account_name),
            'isCancelled' => (bool) $this->is_cancelled,
        ];
    }
}
