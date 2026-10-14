<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankLedgerEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rekening bank/kas beserta buku besar dan rekening koran (PRD §4.5).
 */
final class BankAccountService
{
    private const LEDGER_LIMIT = 200;

    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * @return Collection<int, BankAccount>
     */
    public function list(?string $search = null): Collection
    {
        $query = BankAccount::query()->with(['accountType', 'branch']);

        $search = trim((string) $search);

        if ($search !== '') {
            $term = '%'.$search.'%';

            $query->where(fn (Builder $query) => $query
                ->where('account_name', 'like', $term)
                ->orWhere('bank_name', 'like', $term)
                ->orWhere('account_no', 'like', $term));
        }

        return $query->orderBy('account_name')->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload): BankAccount
    {
        $account = BankAccount::query()->create($this->attributes($payload));

        return $account->load(['accountType', 'branch']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(BankAccount $account, array $payload): BankAccount
    {
        $account->forceFill($this->attributes($payload, $account))->save();

        return $account->refresh()->load(['accountType', 'branch']);
    }

    public function delete(BankAccount $account): void
    {
        $account->delete();
    }

    /**
     * @return Collection<int, BankLedgerEntry>
     */
    public function ledger(BankAccount $account): Collection
    {
        return $account->ledgerEntries()
            ->orderByDesc('id')
            ->limit(self::LEDGER_LIMIT)
            ->get();
    }

    /**
     * Ringkasan rekening koran: saldo awal, total mutasi, dan saldo berjalan.
     *
     * @return array{
     *     account: BankAccount,
     *     openingBalance: int,
     *     totalCredit: int,
     *     totalDebit: int,
     *     currentBalance: int,
     *     entries: Collection<int, BankLedgerEntry>
     * }
     */
    public function statement(BankAccount $account): array
    {
        $account->loadMissing(['accountType', 'branch']);

        $totals = $this->ledger->totals($account);

        return [
            'account' => $account,
            'openingBalance' => (int) $account->opening_balance,
            'totalCredit' => $totals['credit'],
            'totalDebit' => $totals['debit'],
            'currentBalance' => $this->ledger->balanceFrom((int) $account->opening_balance, $totals),
            'entries' => $this->ledger($account),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function attributes(array $payload, ?BankAccount $account = null): array
    {
        return [
            'branch_id' => $payload['branchId'] ?? $account?->branch_id,
            'bank_account_type_id' => $payload['accountTypeId'] ?? $account?->bank_account_type_id,
            'account_name' => $payload['accountName'],
            'bank_name' => $payload['bankName'] ?? null,
            'account_no' => $payload['accountNo'] ?? null,
            'opening_balance' => (int) ($payload['openingBalance'] ?? $account?->opening_balance ?? 0),
            'ifsc' => $payload['ifsc'] ?? null,
            'description' => $payload['description'] ?? null,
            'status' => $payload['status'] ?? $account?->status ?? 'Active',
        ];
    }
}
