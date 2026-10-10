<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
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
            'customer' => ['required', 'string', 'max:255'],
            'date' => ['sometimes', 'nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['sometimes', 'nullable', 'integer'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'integer', 'min:0'],
            'items.*.unit' => ['sometimes', 'nullable', 'string', 'max:30'],
            'items.*.spec' => ['sometimes', 'nullable', 'string', 'max:500'],

            'deliveryFee' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'discount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'delivery' => ['sometimes', 'nullable', 'in:Pickup,Pick Up,Shipping,Courier,Express'],
            'channel' => ['sometimes', 'nullable', 'in:POS,Website,Quotation'],
            'method' => ['sometimes', 'nullable', 'in:Cash,Bank Transfer,Transfer,Debit Card'],
            'paidAmount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
