<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesReturnRequest extends FormRequest
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
            'salesNo' => ['sometimes', 'nullable', 'string', 'max:60'],
            'date' => ['sometimes', 'nullable', 'date'],
            'customer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'items.*.qtyOrder' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items.*.qtyReturn' => ['required', 'numeric', 'min:0'],
            'items.*.unit' => ['sometimes', 'nullable', 'string', 'max:30'],
            'items.*.price' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'items.*.returnAmount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'items.*.reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
