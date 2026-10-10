<?php

namespace App\Models;

use Database\Factories\BankLedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankLedgerEntry extends Model
{
    /** @use HasFactory<BankLedgerEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'bank_account_id',
        'branch_id',
        'direction',
        'amount',
        'occurred_at',
        'reference_type',
        'reference_id',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
