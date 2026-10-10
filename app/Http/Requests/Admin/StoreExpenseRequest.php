<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
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
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'paymentMethod' => ['sometimes', 'nullable', 'string', 'max:40'],
            'bankAccountId' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
            'paid' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
