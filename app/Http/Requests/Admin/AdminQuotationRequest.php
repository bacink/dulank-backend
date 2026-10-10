<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminQuotationRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', 'in:Draft,Pending,Send,Ordered,Rejected,Complete'],
            'channel' => ['sometimes', 'nullable', 'in:Online,Sales Staff,Offline'],
            'dueDate' => ['sometimes', 'nullable', 'date'],
            'total' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'document' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
