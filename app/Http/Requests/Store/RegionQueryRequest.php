<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class RegionQueryRequest extends FormRequest
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
            'province' => ['sometimes', 'nullable', 'string', 'max:20'],
            'regency' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
