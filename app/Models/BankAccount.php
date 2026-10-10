<?php

namespace App\Models;

use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'bank_account_type_id',
        'account_name',
        'bank_name',
        'account_no',
        'opening_balance',
        'ifsc',
        'description',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_balance' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'branch_id');
    }

    /**
     * @return BelongsTo<BankAccountType, $this>
     */
    public function accountType(): BelongsTo
    {
        return $this->belongsTo(BankAccountType::class, 'bank_account_type_id');
    }

    /**
     * @return HasMany<BankLedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(BankLedgerEntry::class);
    }

    /**
     * Transfer keluar dari akun ini.
     *
     * @return HasMany<MoneyTransfer, $this>
     */
    public function transfersOut(): HasMany
    {
        return $this->hasMany(MoneyTransfer::class, 'from_account_id');
    }

    /**
     * Transfer masuk ke akun ini.
     *
     * @return HasMany<MoneyTransfer, $this>
     */
    public function transfersIn(): HasMany
    {
        return $this->hasMany(MoneyTransfer::class, 'to_account_id');
    }
}
