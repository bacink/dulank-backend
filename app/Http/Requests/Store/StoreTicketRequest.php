<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('priority') && is_string($this->input('priority'))) {
            $this->merge(['priority' => strtolower($this->input('priority'))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'type' => ['sometimes', 'nullable', 'string', 'max:60'],
            'priority' => ['sometimes', 'nullable', 'in:high,medium,low'],
        ];
    }
}
