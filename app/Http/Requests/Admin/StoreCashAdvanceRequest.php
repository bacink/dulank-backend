<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashAdvanceRequest extends FormRequest
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
            'employeeId' => ['sometimes', 'nullable', 'integer'],
            'employeeName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bankAccountId' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
            'date' => ['sometimes', 'nullable', 'date'],
            'totalCash' => ['required', 'integer', 'min:1'],
            'installmentCount' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'period' => ['sometimes', 'nullable', 'in:Daily,Weekly,Monthly'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
