<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductType;
use App\Http\Requests\Shared\ListQueryRequest;
use Illuminate\Validation\Rule;

class AdminIndexRequest extends ListQueryRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'productType' => ['sometimes', 'nullable', Rule::enum(ProductType::class)],
            'status' => ['sometimes', 'nullable', 'string', 'max:60'],
            'type' => ['sometimes', 'nullable', 'string', 'max:60'],
            'paymentStatus' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);
    }
}
