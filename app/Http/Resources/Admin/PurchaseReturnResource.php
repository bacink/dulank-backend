<?php

namespace App\Http\Resources\Admin;

use App\Models\PurchaseReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseReturn
 */
class PurchaseReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'noPR' => $this->no_pr,
            'date' => $this->date?->toDateString(),
            'created' => $this->created_at?->toIso8601String(),
            'noPurchase' => $this->no_purchase,
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier?->name),
            'amount' => (int) $this->amount,
            'paid' => (int) $this->paid,
            'due' => (int) $this->due,
            'status' => $this->status,
            'statusBy' => $this->status_by,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'name' => $item->name,
                'qty' => (float) $item->qty,
                'returnQty' => (float) $item->return_qty,
                'unit' => $item->unit,
                'price' => (int) $item->price,
                'amount' => (int) $item->amount,
                'reason' => $item->reason,
            ])->values()),
        ];
    }
}
