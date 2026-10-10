<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreWishlistRequest extends FormRequest
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
            'productId' => ['required', 'integer', 'exists:products,id'],
            'spec' => ['sometimes', 'nullable', 'string', 'max:500'],
            'expireDate' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
