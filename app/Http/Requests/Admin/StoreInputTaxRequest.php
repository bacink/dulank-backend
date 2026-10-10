<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreInputTaxRequest extends FormRequest
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
            'purchaseId' => ['sometimes', 'nullable', 'integer', 'exists:purchases,id'],
            'invoiceDate' => ['required', 'date'],
            'fakturNo' => ['sometimes', 'nullable', 'string', 'max:60'],
            'credited' => ['sometimes', 'nullable', 'in:Yes,No'],
        ];
    }
}
