<?php

namespace App\Http\Resources\Admin;

use App\Models\DeliveryNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeliveryNote
 */
class DeliveryNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'dnNo' => $this->dn_no,
            'date' => $this->date?->toDateString(),
            'customer' => $this->customer,
            'noSales' => $this->no_sales,
            'shippingAddress' => $this->shipping_address,
            'status' => $this->status,
            'dateStatus' => $this->date_status?->toDateString(),
            'po' => $this->po,
            'shippingBy' => $this->shipping_by,
            'reference' => $this->reference,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'description' => $item->description,
                'qty' => (float) $item->qty,
                'unit' => $item->unit,
                'packingQty' => $item->packing_qty,
                'weight' => $item->weight,
            ])->values()),
            'receiveBy' => $this->receive_by,
            'security' => $this->security,
            'driver' => $this->driver,
            'issuedBy' => $this->issued_by,
        ];
    }
}
