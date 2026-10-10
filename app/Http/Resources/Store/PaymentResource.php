<?php

namespace App\Http\Resources\Store;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payable = $this->whenLoaded('payable');

        return [
            'id' => $this->id,
            'refNo' => $this->ref_no,
            'date' => $this->date?->toDateString(),
            'amount' => (int) $this->amount,
            'method' => $this->method,
            'status' => $this->status?->value,
            'notes' => $this->notes,
            'proofUrl' => app(MediaService::class)->url($this->proof_path),
            'payableType' => match (true) {
                $this->payable_type === Invoice::class => 'invoice',
                $this->payable_type === Order::class => 'order',
                default => null,
            },
            'payableId' => match (true) {
                $payable instanceof Invoice => $payable->invoice_no,
                $payable instanceof Order => $payable->number,
                default => null,
            },
        ];
    }
}
