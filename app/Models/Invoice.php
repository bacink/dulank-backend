<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentVerification;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'invoice_no',
        'order_id',
        'user_id',
        'customer_id',
        'invoice_date',
        'due_date',
        'amount',
        'paid',
        'amount_due',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'amount' => 'integer',
            'paid' => 'integer',
            'amount_due' => 'integer',
            'status' => InvoiceStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Hitung ulang paid/amount_due/status dari pembayaran terverifikasi.
     */
    public function recalculate(): void
    {
        $paid = (int) $this->payments()
            ->where('status', PaymentVerification::Verified)
            ->sum('amount');
        $due = max($this->amount - $paid, 0);

        $status = match (true) {
            $paid <= 0 => InvoiceStatus::Unpaid,
            $due > 0 => InvoiceStatus::Partial,
            default => InvoiceStatus::Paid,
        };

        $this->forceFill([
            'paid' => $paid,
            'amount_due' => $due,
            'status' => $status,
        ])->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
