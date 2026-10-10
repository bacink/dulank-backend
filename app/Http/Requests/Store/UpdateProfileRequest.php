<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'companyType' => ['sometimes', 'nullable', 'string', 'max:50'],
            'companyName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'departmentName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'npwp' => ['sometimes', 'nullable', 'string', 'max:32'],
            'transactionCode' => ['sometimes', 'nullable', 'string', 'max:120'],
            'taxAddress' => ['sometimes', 'nullable', 'string', 'max:500'],
            'attachTaxInvoice' => ['sometimes', 'boolean'],
        ];
    }
}
