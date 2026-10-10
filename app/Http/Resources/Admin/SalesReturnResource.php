<?php

namespace App\Http\Resources\Admin;

use App\Models\SalesReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SalesReturn
 */
class SalesReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'returnNo' => $this->return_no,
            'date' => $this->date?->toDateString(),
            'salesNo' => $this->order?->number,
            'customer' => $this->customer,
            'paymentStatus' => $this->payment_status,
            'paymentDate' => $this->payment_date?->toDateString(),
            'paymentMethod' => $this->payment_method,
            'total' => (int) $this->total,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'name' => $item->name,
                'description' => $item->description,
                'qtyOrder' => (float) $item->qty_order,
                'qtyReturn' => (float) $item->qty_return,
                'unit' => $item->unit,
                'price' => (int) $item->price,
                'returnAmount' => (int) $item->return_amount,
                'reason' => $item->reason,
            ])->values()),
            'notes' => $this->notes,
            'payment' => $this->whenLoaded('refundPayment', fn () => $this->refundPayment === null ? null : [
                'method' => $this->refundPayment->method,
                'amount' => (int) $this->refundPayment->amount,
                'reference' => $this->refundPayment->ref_no,
                'notes' => $this->refundPayment->notes,
            ]),
        ];
    }
}
