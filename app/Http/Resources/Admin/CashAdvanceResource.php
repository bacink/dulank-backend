<?php

namespace App\Http\Resources\Admin;

use App\Models\CashAdvance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashAdvance
 */
class CashAdvanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payments = $this->whenLoaded('payments', fn () => $this->payments
            ->sortBy('date')
            ->values()
            ->map(fn ($payment, int $index) => [
                'id' => (string) $payment->id,
                'date' => $payment->date?->toDateString(),
                'amount' => (int) $payment->amount,
                'installment' => $index + 1,
                'period' => $this->period,
                'tenorTotal' => (int) $this->installment_count,
                'note' => $payment->note,
                'status' => $index + 1 >= (int) $this->installment_count ? 'Close' : 'On',
            ]), []);

        $paid = $this->whenLoaded('payments', fn () => (int) $this->payments->sum('amount'), 0);
        $installmentAmount = (int) $this->installment_count > 0
            ? (int) round((int) $this->total_cash / (int) $this->installment_count)
            : (int) $this->total_cash;
        $paidCount = $this->whenLoaded('payments', fn () => $this->payments->count(), 0);

        return [
            'id' => (string) $this->id,
            'employeeId' => $this->employee_id !== null ? (string) $this->employee_id : null,
            'employeeName' => $this->employee_name,
            'bankAccountId' => $this->bank_account_id !== null ? (string) $this->bank_account_id : null,
            'date' => $this->date?->toDateString(),
            'totalCash' => (int) $this->total_cash,
            'installmentCount' => (int) $this->installment_count,
            'period' => $this->period,
            'note' => $this->note,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'history' => $payments,
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => [
                'id' => (string) $payment->id,
                'date' => $payment->date?->toDateString(),
                'amount' => (int) $payment->amount,
                'referenceId' => $payment->reference_id,
            ])->values(), []),
            'employee' => $this->employee_name,
            'installmentAmount' => $installmentAmount,
            'totalPaid' => $paid,
            'outstanding' => max((int) $this->total_cash - $paid, 0),
            'tenorRemain' => max((int) $this->installment_count - $paidCount, 0),
            'status' => $paid >= (int) $this->total_cash && $paid > 0 ? 'Close' : 'On',
        ];
    }
}
