<?php

namespace App\Services;

use App\Enums\PaymentDirection;
use App\Enums\PaymentStatus;
use App\Enums\PaymentVerification;
use App\Exceptions\ApiException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\User;
use App\Support\DocumentNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Pembayaran: pengajuan bukti dari store + verifikasi/pencatatan oleh admin.
 *
 * Hanya payment berstatus Verified yang dihitung ke `paid` (PRD §7.4).
 */
final class PaymentService
{
    public function __construct(private readonly MediaService $media) {}

    /**
     * Pengajuan bukti pembayaran dari store (status Pending).
     *
     * @param  array<string, mixed>  $payload
     */
    public function submit(User $user, array $payload, UploadedFile $proof): Payment
    {
        return DB::transaction(function () use ($user, $payload, $proof): Payment {
            $order = null;
            $maxAmount = 0;

            if ($payload['payableType'] === 'invoice') {
                $invoice = Invoice::query()
                    ->where('invoice_no', $payload['payableId'])
                    ->where('user_id', $user->id)
                    ->first();

                if ($invoice === null) {
                    throw ApiException::notFound('Invoice not found.');
                }

                $payable = $invoice;
                $order = $invoice->order;
                $maxAmount = (int) $invoice->amount_due;
            } else {
                $order = Order::query()
                    ->where('number', $payload['payableId'])
                    ->where('user_id', $user->id)
                    ->first();

                if ($order === null) {
                    throw ApiException::notFound('Order not found.');
                }

                $payable = $order;
                $maxAmount = (int) $order->due;
            }

            $amount = (int) $payload['amount'];

            if ($amount > $maxAmount) {
                throw ApiException::validation('Nominal melebihi sisa tagihan.', [
                    'amount' => ['Nominal melebihi sisa tagihan (maksimal '.number_format($maxAmount, 0, ',', '.').').'],
                ]);
            }

            $payment = Payment::query()->create([
                'payable_type' => $payable::class,
                'payable_id' => $payable->getKey(),
                'direction' => PaymentDirection::In,
                'ref_no' => DocumentNumber::next('payment', Payment::query(), 'ref_no', 5),
                'user_id' => $user->id,
                'date' => now()->toDateString(),
                'amount' => $amount,
                'method' => $payload['method'] ?? 'Transfer',
                'proof_path' => $this->media->store($proof, 'payments/'.$user->id),
                'status' => PaymentVerification::Pending,
                'notes' => $payload['notes'] ?? null,
            ]);

            if ($order !== null) {
                $order->statusHistories()->create([
                    'status' => 'payment-submitted',
                    'note' => 'Bukti pembayaran '.$payment->ref_no.' diunggah (menunggu verifikasi).',
                    'occurred_at' => now(),
                    'created_by' => $user->id,
                ]);
            }

            return $payment->load('payable');
        });
    }

    /**
     * Verifikasi pembayaran oleh admin; menghitung ulang invoice/order.
     */
    public function verify(Payment $payment, User $admin, ?string $note = null): Payment
    {
        if ($payment->status === PaymentVerification::Verified) {
            throw ApiException::conflict('Pembayaran sudah diverifikasi.');
        }

        return DB::transaction(function () use ($payment, $admin, $note): Payment {
            $payment->forceFill([
                'status' => PaymentVerification::Verified,
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ])->save();

            $this->applyToPayable($payment, $admin, 'payment-verified', $note);

            return $payment->refresh()->load('payable');
        });
    }

    /**
     * Tolak bukti pembayaran; invoice/order tidak berubah.
     */
    public function reject(Payment $payment, User $admin, ?string $note = null): Payment
    {
        if ($payment->status === PaymentVerification::Rejected) {
            throw ApiException::conflict('Pembayaran sudah ditolak sebelumnya.');
        }

        return DB::transaction(function () use ($payment, $admin, $note): Payment {
            $payment->forceFill([
                'status' => PaymentVerification::Rejected,
                'verified_at' => now(),
                'verified_by' => $admin->id,
                'notes' => $note ?? $payment->notes,
            ])->save();

            $this->applyToPayable($payment, $admin, 'payment-rejected', $note);

            return $payment->refresh()->load('payable');
        });
    }

    /**
     * Pencatatan pembayaran langsung oleh admin (langsung Verified).
     */
    public function record(
        User $admin,
        Invoice|Order|Purchase|PurchaseReturn $payable,
        int $amount,
        string $method = 'Cash',
        ?string $date = null,
        ?string $notes = null,
        PaymentDirection $direction = PaymentDirection::In,
    ): Payment {
        return DB::transaction(function () use ($admin, $payable, $amount, $method, $date, $notes, $direction): Payment {
            $payment = Payment::query()->create([
                'payable_type' => $payable::class,
                'payable_id' => $payable->getKey(),
                'direction' => $direction,
                'ref_no' => DocumentNumber::next('payment', Payment::query(), 'ref_no', 5),
                'user_id' => $payable instanceof Purchase || $payable instanceof PurchaseReturn ? null : $payable->user_id,
                'date' => $date ?? now()->toDateString(),
                'amount' => $amount,
                'method' => $method,
                'status' => PaymentVerification::Verified,
                'verified_at' => now(),
                'verified_by' => $admin->id,
                'notes' => $notes,
            ]);

            $this->applyToPayable($payment, $admin, 'payment-recorded', null);

            return $payment->refresh()->load('payable');
        });
    }

    private function applyToPayable(Payment $payment, User $admin, string $historyStatus, ?string $note): void
    {
        $payable = $payment->payable;
        $order = null;

        if ($payable instanceof Invoice) {
            $payable->recalculate();
            $order = $payable->order;
        } elseif ($payable instanceof Order) {
            $this->recalculateOrder($payable);
            $order = $payable;
        } elseif ($payable instanceof Purchase) {
            $this->recalculatePurchase($payable);
        } elseif ($payable instanceof PurchaseReturn) {
            $this->recalculatePurchaseReturn($payable);
        }

        if ($order !== null) {
            $order->statusHistories()->create([
                'status' => $historyStatus,
                'note' => $note,
                'occurred_at' => now(),
                'created_by' => $admin->id,
            ]);
        }
    }

    private function recalculatePurchase(Purchase $purchase): void
    {
        $paid = (int) $purchase->payments()
            ->where('status', PaymentVerification::Verified)
            ->sum('amount');
        $due = max((int) $purchase->amount - $paid, 0);

        $purchase->forceFill([
            'paid' => $paid,
            'due' => $due,
            'payment_status' => match (true) {
                $paid <= 0 => 'Unpaid',
                $due > 0 => 'Partial',
                default => 'Paid',
            },
        ])->save();
    }

    private function recalculatePurchaseReturn(PurchaseReturn $return): void
    {
        $paid = (int) $return->payments()
            ->where('status', PaymentVerification::Verified)
            ->sum('amount');
        $due = max((int) $return->amount - $paid, 0);

        $return->forceFill([
            'paid' => $paid,
            'due' => $due,
            'status' => $due <= 0 && $paid > 0 ? 'Refunded' : $return->status,
        ])->save();
    }

    private function recalculateOrder(Order $order): void
    {
        $paid = (int) $order->payments()
            ->where('status', PaymentVerification::Verified)
            ->sum('amount');
        $due = max((int) $order->total - $paid, 0);

        $status = match (true) {
            $paid <= 0 => PaymentStatus::Unpaid,
            $due > 0 => PaymentStatus::Partial,
            default => PaymentStatus::Paid,
        };

        $order->forceFill([
            'paid' => $paid,
            'due' => $due,
            'payment_status' => $status,
        ])->save();
    }
}
