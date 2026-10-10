<?php

namespace App\Http\Resources\Admin;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'noExpense' => $this->no_expense,
            'date' => $this->date?->toDateString(),
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'name' => $this->name,
            'status' => $this->status,
            'amount' => (int) $this->amount,
            'paid' => (int) $this->paid,
            'due' => (int) $this->due,
            'description' => $this->description,
            'paymentMethod' => $this->payment_method,
            'bankAccountId' => $this->bank_account_id !== null ? (string) $this->bank_account_id : null,
            'bankAccount' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount?->account_name),
        ];
    }
}
