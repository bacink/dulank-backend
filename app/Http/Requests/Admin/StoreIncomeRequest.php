<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncomeRequest extends FormRequest
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
            'date' => ['sometimes', 'nullable', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'amount' => ['required', 'integer', 'min:1'],
            'paymentMethod' => ['sometimes', 'nullable', 'string', 'max:40'],
            'bankAccountId' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
        ];
    }
}
