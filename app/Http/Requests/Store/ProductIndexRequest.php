<?php

namespace App\Http\Requests\Store;

use App\Http\Requests\Shared\ListQueryRequest;

class ProductIndexRequest extends ListQueryRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }
}
