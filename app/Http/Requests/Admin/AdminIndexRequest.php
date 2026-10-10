<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Shared\ListQueryRequest;

class AdminIndexRequest extends ListQueryRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => ['sometimes', 'nullable', 'string', 'max:60'],
            'type' => ['sometimes', 'nullable', 'string', 'max:60'],
            'paymentStatus' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);
    }
}
