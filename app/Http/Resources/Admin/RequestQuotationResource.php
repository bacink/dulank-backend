<?php

namespace App\Http\Resources\Admin;

use App\Models\RequestQuotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RequestQuotation
 */
class RequestQuotationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'noRequest' => $this->no_request,
            'customer' => $this->customer,
            'email' => $this->email,
            'telp' => $this->telp,
            'date' => $this->date?->toDateString(),
            'status' => $this->status,
            'dueDate' => $this->due_date?->toDateString(),
            'paymentTerm' => $this->payment_term,
            'document' => $this->document,
        ];
    }
}
