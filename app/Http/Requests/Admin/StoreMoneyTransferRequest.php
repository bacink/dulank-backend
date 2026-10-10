<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreMoneyTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'fromAccountId' => ['required', 'integer', 'exists:bank_accounts,id'],
            'toAccountId' => ['required', 'integer', 'different:fromAccountId', 'exists:bank_accounts,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'occurredAt' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
