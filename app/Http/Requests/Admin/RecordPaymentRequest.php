<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['sometimes', 'nullable', 'string', 'max:40'],
            'date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'payableType' => ['sometimes', 'nullable', 'in:invoice,order'],
            'payableId' => ['sometimes', 'nullable', 'string', 'max:60'],
        ];
    }
}
