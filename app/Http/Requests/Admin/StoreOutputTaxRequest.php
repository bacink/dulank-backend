<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreOutputTaxRequest extends FormRequest
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
            'saleId' => ['sometimes', 'nullable', 'string', 'max:60'],
            'etaxDate' => ['required', 'date'],
            'etaxNumber' => ['sometimes', 'nullable', 'string', 'max:60'],
            'txCode' => ['sometimes', 'nullable', 'string', 'max:60'],
            'status' => ['sometimes', 'nullable', 'in:Issued,Draft,Cancelled'],
        ];
    }
}
