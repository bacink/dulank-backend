<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryNoteRequest extends FormRequest
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
            'noSales' => ['sometimes', 'nullable', 'string', 'max:60'],
            'customer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date' => ['sometimes', 'nullable', 'date'],
            'shippingAddress' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'nullable', 'in:Complete,Pending,Ordered,Received'],
            'po' => ['sometimes', 'nullable', 'string', 'max:60'],
            'shippingBy' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'receiveBy' => ['sometimes', 'nullable', 'string', 'max:255'],
            'security' => ['sometimes', 'nullable', 'string', 'max:255'],
            'driver' => ['sometimes', 'nullable', 'string', 'max:255'],
            'issuedBy' => ['sometimes', 'nullable', 'string', 'max:255'],

            'items' => ['sometimes', 'nullable', 'array'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0'],
            'items.*.unit' => ['sometimes', 'nullable', 'string', 'max:30'],
            'items.*.packingQty' => ['sometimes', 'nullable', 'string', 'max:60'],
            'items.*.weight' => ['sometimes', 'nullable', 'string', 'max:60'],
        ];
    }
}
