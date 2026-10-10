<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
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
            'contact' => ['sometimes', 'nullable', 'array'],
            'contact.name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact.phone' => ['sometimes', 'nullable', 'string', 'max:30'],

            'shippingAddress' => ['required', 'array'],
            'shippingAddress.name' => ['required', 'string', 'max:255'],
            'shippingAddress.phone' => ['required', 'string', 'max:30'],
            'shippingAddress.type' => ['sometimes', 'nullable', 'string', 'max:30'],
            'shippingAddress.street' => ['required', 'string', 'max:500'],
            'shippingAddress.district' => ['sometimes', 'nullable', 'string', 'max:120'],
            'shippingAddress.city' => ['required', 'string', 'max:120'],
            'shippingAddress.province' => ['sometimes', 'nullable', 'string', 'max:120'],
            'shippingAddress.postalCode' => ['sometimes', 'nullable', 'string', 'max:10'],
            'shippingAddress.country' => ['sometimes', 'nullable', 'string', 'max:10'],

            'shippingMethod' => ['sometimes', 'nullable', 'in:Pick Up,Shipping,Courier,Express'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'voucherCode' => ['sometimes', 'nullable', 'string', 'max:60'],

            'items' => ['sometimes', 'nullable', 'array'],
            'items.*.cartItemId' => ['required', 'integer'],
            'items.*.artwork' => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.printResult' => ['sometimes', 'nullable', 'string', 'max:255'],
            'items.*.note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
