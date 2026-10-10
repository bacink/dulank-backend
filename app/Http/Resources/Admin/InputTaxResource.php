<?php

namespace App\Http\Resources\Admin;

use App\Models\InputTaxDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InputTaxDocument
 */
class InputTaxResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $purchase = $this->whenLoaded('purchase');

        return [
            'id' => (string) $this->id,
            'purchaseId' => $this->purchase_id !== null ? (string) $this->purchase_id : null,
            'invoiceDate' => $this->invoice_date?->toDateString(),
            'fakturNo' => $this->faktur_no,
            'credited' => $this->credited,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'purchaseNo' => $purchase?->no_purchase,
            'supplierName' => $purchase?->supplier?->name,
            'dpp' => $purchase !== null ? (int) $purchase->amount : 0,
            'vat' => $purchase !== null ? (int) $purchase->tax : 0,
        ];
    }
}
