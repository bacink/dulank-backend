<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
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
            'status' => ['required', 'string', 'max:60'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'statusBy' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
