<?php

namespace App\Models;

use Database\Factories\CashAdvancePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashAdvancePayment extends Model
{
    /** @use HasFactory<CashAdvancePaymentFactory> */
    use HasFactory;

    protected $fillable = ['cash_advance_id', 'date', 'amount', 'reference_id', 'note'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CashAdvance, $this>
     */
    public function cashAdvance(): BelongsTo
    {
        return $this->belongsTo(CashAdvance::class);
    }
}
