<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
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
            'label' => ['sometimes', 'nullable', 'string', 'max:60'],
            'recipientName' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'type' => ['sometimes', 'nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:500'],
            'district' => ['sometimes', 'nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'province' => ['sometimes', 'nullable', 'string', 'max:120'],
            'postalCode' => ['sometimes', 'nullable', 'string', 'max:10'],
            'country' => ['sometimes', 'nullable', 'string', 'max:10'],
            'isDefault' => ['sometimes', 'boolean'],
        ];
    }
}
