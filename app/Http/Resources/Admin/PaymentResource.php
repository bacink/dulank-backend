<?php

namespace App\Http\Resources\Admin;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SalesReturn;
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
            'id' => (string) $this->id,
            'date' => $this->date?->toDateString(),
            'refNo' => $this->ref_no,
            'name' => $this->user?->name,
            'type' => $this->direction?->value === 'out' ? 'Payment-Out' : 'Payment-In',
            'method' => $this->method,
            'amount' => (int) $this->amount,
            'status' => $this->status?->value,
            'verifiedAt' => $this->verified_at?->toIso8601String(),
            'proofUrl' => app(MediaService::class)->url($this->proof_path),
            'payableType' => match (true) {
                $this->payable_type === Invoice::class => 'invoice',
                $this->payable_type === Order::class => 'order',
                $this->payable_type === SalesReturn::class => 'sales_return',
                default => null,
            },
            'payableId' => match (true) {
                $payable instanceof Invoice => $payable->invoice_no,
                $payable instanceof Order => $payable->number,
                $payable instanceof SalesReturn => $payable->return_no,
                default => null,
            },
            'notes' => $this->notes,
            'created' => $this->created_at?->toIso8601String(),
        ];
    }
}
