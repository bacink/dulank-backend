<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shared\ListQueryRequest;

class OrderIndexRequest extends ListQueryRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => ['sometimes', 'nullable', 'string', 'max:60'],
            'shipping' => ['sometimes', 'nullable', 'string', 'max:30'],
            'channel' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);
    }
}
