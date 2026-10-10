<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
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
            'noPO' => ['sometimes', 'nullable', 'string', 'max:60'],
            'date' => ['sometimes', 'nullable', 'date'],
            'noPurchase' => ['sometimes', 'nullable', 'string', 'max:60'],
            'supplier' => ['required', 'string', 'max:255'],
            'poStatus' => ['sometimes', 'nullable', 'in:Sent,Draft,Cancel'],
            'goodsStatus' => ['sometimes', 'nullable', 'in:Scheduled,Complete,Cancel,Pending'],
            'goodsDate' => ['sometimes', 'nullable', 'date'],
            'goodsBy' => ['sometimes', 'nullable', 'string', 'max:255'],
            'termOfPayment' => ['sometimes', 'nullable', 'string', 'max:120'],
            'deliveryDate' => ['sometimes', 'nullable', 'date'],
            'deliveryAddress' => ['sometimes', 'nullable', 'string', 'max:500'],
            'vendorReff' => ['sometimes', 'nullable', 'string', 'max:120'],
            'taxRate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'amount' => ['sometimes', 'nullable', 'integer', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['sometimes', 'nullable', 'string', 'max:30'],
            'items.*.price' => ['required', 'integer', 'min:0'],
        ];
    }
}
