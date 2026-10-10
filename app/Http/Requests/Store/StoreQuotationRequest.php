<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['sometimes', 'nullable', 'integer', 'exists:products,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unitPrice' => ['required', 'integer', 'min:0'],
            'items.*.spec' => ['sometimes', 'nullable', 'string', 'max:500'],
            'items.*.artwork' => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.printResult' => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.note' => ['sometimes', 'nullable', 'string', 'max:1000'],

            'customerInfo' => ['sometimes', 'nullable', 'array'],
            'customerInfo.name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'customerInfo.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'customerInfo.phone' => ['sometimes', 'nullable', 'string', 'max:30'],

            'validUntil' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
            'shippingCost' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
