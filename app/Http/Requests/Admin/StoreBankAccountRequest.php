<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankAccountRequest extends FormRequest
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
            'id' => ['sometimes', 'nullable'],
            'branchId' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            'accountTypeId' => ['sometimes', 'nullable', 'integer', 'exists:bank_account_types,id'],
            'accountName' => ['required', 'string', 'max:255'],
            'bankName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'accountNo' => ['sometimes', 'nullable', 'string', 'max:60'],
            'openingBalance' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'ifsc' => ['sometimes', 'nullable', 'string', 'max:60'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ];
    }
}
