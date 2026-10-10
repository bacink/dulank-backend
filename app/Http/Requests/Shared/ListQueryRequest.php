<?php

namespace App\Http\Requests\Shared;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query list standar: page, limit/per_page, search, sort, order, startDate, endDate.
 */
class ListQueryRequest extends FormRequest
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
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:50'],
            'order' => ['sometimes', 'nullable', 'in:asc,desc'],
            'startDate' => ['sometimes', 'nullable', 'date'],
            'endDate' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function page(): int
    {
        return max((int) $this->input('page', 1), 1);
    }

    public function limit(): int
    {
        $limit = (int) ($this->input('limit') ?? $this->input('per_page') ?? $this->defaultLimit());

        return min(max($limit, 1), (int) config('dulank.pagination.max', 100));
    }

    public function defaultLimit(): int
    {
        return (int) config('dulank.pagination.default', 20);
    }
}
