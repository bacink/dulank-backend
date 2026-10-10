<?php

namespace App\Http\Resources\Admin;

use App\Models\OutputTaxDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OutputTaxDocument
 */
class OutputTaxResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->whenLoaded('order');

        return [
            'id' => (string) $this->id,
            'saleId' => $this->order_id !== null ? (string) $this->order_id : null,
            'etaxDate' => $this->etax_date?->toDateString(),
            'etaxNumber' => $this->etax_number,
            'txCode' => $this->tx_code,
            'status' => $this->status,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'salesNo' => $order?->number,
            'customerName' => $order?->contact['name'] ?? null,
            'dpp' => $order !== null ? (int) $order->sub_total : 0,
            'vat' => $order !== null ? (int) $order->tax : 0,
            'total' => $order !== null ? (int) $order->total : 0,
        ];
    }
}
