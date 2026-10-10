<?php

namespace App\Models;

use Database\Factories\IncomeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    /** @use HasFactory<IncomeFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
        'no',
        'name',
        'income_category_id',
        'notes',
        'amount',
        'payment_method',
        'bank_account_id',
        'is_cancelled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'integer',
            'is_cancelled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<IncomeCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(IncomeCategory::class, 'income_category_id');
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
