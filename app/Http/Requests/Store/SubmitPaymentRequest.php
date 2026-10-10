<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPaymentRequest extends FormRequest
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
            'payableType' => ['required', 'in:invoice,order'],
            'payableId' => ['required', 'string', 'max:60'],
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['sometimes', 'nullable', 'string', 'max:40'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.(int) config('dulank.uploads.max_kb', 4096)],
        ];
    }
}
