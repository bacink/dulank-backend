<?php

namespace App\Http\Resources\Admin;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'noPO' => $this->no_po,
            'date' => $this->date?->toDateString(),
            'created' => $this->created_by_name,
            'noPurchase' => $this->no_purchase,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier?->name),
            'amount' => (int) $this->amount,
            'poStatus' => $this->po_status,
            'goodsStatus' => $this->goods_status,
            'goodsDate' => $this->goods_date?->toDateString(),
            'goodsBy' => $this->goods_by,
            'termOfPayment' => $this->term_of_payment,
            'deliveryDate' => $this->delivery_date?->toDateString(),
            'deliveryAddress' => $this->delivery_address,
            'vendorReff' => $this->vendor_reff,
            'taxRate' => (float) $this->tax_rate,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'name' => $item->name,
                'qty' => (float) $item->qty,
                'unit' => $item->unit,
                'price' => (int) $item->price,
            ])->values()),
        ];
    }
}
