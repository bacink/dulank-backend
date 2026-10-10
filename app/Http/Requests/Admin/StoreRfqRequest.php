<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRfqRequest extends FormRequest
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
            'customer' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'telp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'in:Ordered,Complete,Pending,Received'],
            'dueDate' => ['sometimes', 'nullable', 'date'],
            'paymentTerm' => ['sometimes', 'nullable', 'string', 'max:120'],
            'document' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
