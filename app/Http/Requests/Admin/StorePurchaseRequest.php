<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
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
            'noPurchase' => ['sometimes', 'nullable', 'string', 'max:60'],
            'date' => ['sometimes', 'nullable', 'date'],
            'supplier' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Received,Complete,Ordered,Pending'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'shippingCost' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'tax' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'paid' => ['sometimes', 'nullable', 'integer', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['sometimes', 'nullable', 'string', 'max:30'],
            'items.*.price' => ['required', 'integer', 'min:0'],
        ];
    }
}
