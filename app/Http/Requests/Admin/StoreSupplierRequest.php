<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplierId' => [
                'sometimes', 'nullable', 'string', 'max:60',
                Rule::unique('suppliers', 'code')->ignore($this->route('supplierId'), 'code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact' => ['sometimes', 'nullable', 'string', 'max:60'],
            'picName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive,Deactive'],
            'date' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
