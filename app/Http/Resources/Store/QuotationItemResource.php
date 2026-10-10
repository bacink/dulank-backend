<?php

namespace App\Http\Resources\Store;

use App\Models\QuotationItem;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuotationItem
 */
class QuotationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'productId' => $this->product_id !== null ? (int) $this->product_id : null,
            'name' => $this->product_name,
            'category' => $this->category,
            'quantity' => Num::quantity($this->order_qty),
            'spec' => (string) $this->spec,
            'artwork' => $this->artwork,
            'printResult' => $this->print_result,
            'note' => $this->note,
            'unitPrice' => (int) $this->unit_price,
            'total' => (int) $this->amount,
        ];
    }
}
