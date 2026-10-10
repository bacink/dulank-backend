<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseReturnRequest extends FormRequest
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
            'noPR' => ['sometimes', 'nullable', 'string', 'max:60'],
            'date' => ['sometimes', 'nullable', 'date'],
            'noPurchase' => ['required', 'string', 'max:60'],
            'supplier' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Refunded,Cancel,Ordered,Received,Pending'],
            'statusBy' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'paid' => ['sometimes', 'nullable', 'integer', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0'],
            'items.*.returnQty' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['sometimes', 'nullable', 'string', 'max:30'],
            'items.*.price' => ['required', 'integer', 'min:0'],
            'items.*.amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'items.*.reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
