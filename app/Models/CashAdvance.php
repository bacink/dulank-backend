<?php

namespace App\Models;

use Database\Factories\CashAdvanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAdvance extends Model
{
    /** @use HasFactory<CashAdvanceFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'employee_name',
        'bank_account_id',
        'date',
        'total_cash',
        'installment_count',
        'period',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_cash' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return HasMany<CashAdvancePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CashAdvancePayment::class);
    }
}
