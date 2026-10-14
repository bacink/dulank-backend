<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankLedgerEntry;
use Carbon\Carbon;

/**
 * Buku besar bank/kas (PRD §4.5): saldo = opening_balance + credit − debit.
 *
 * Konvensi: credit menambah saldo (uang masuk), debit mengurangi (uang keluar).
 */
final class LedgerService
{
    public function record(
        BankAccount $account,
        string $direction,
        int $amount,
        string $referenceType,
        ?int $referenceId = null,
        ?string $description = null,
        ?Carbon $occurredAt = null,
    ): BankLedgerEntry {
        return BankLedgerEntry::query()->create([
            'bank_account_id' => $account->id,
            'branch_id' => $account->branch_id,
            'direction' => $direction === 'debit' ? 'debit' : 'credit',
            'amount' => $amount,
            'occurred_at' => $occurredAt ?? now(),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
        ]);
    }

    /**
     * Total mutasi masuk (credit) dan keluar (debit) sebuah akun.
     *
     * @return array{credit: int, debit: int}
     */
    public function totals(BankAccount $account): array
    {
        return [
            'credit' => (int) $account->ledgerEntries()->where('direction', 'credit')->sum('amount'),
            'debit' => (int) $account->ledgerEntries()->where('direction', 'debit')->sum('amount'),
        ];
    }

    public function balance(BankAccount $account): int
    {
        return $this->balanceFrom((int) $account->opening_balance, $this->totals($account));
    }

    /**
     * @param  array{credit: int, debit: int}  $totals
     */
    public function balanceFrom(int $openingBalance, array $totals): int
    {
        return $openingBalance + $totals['credit'] - $totals['debit'];
    }
}
