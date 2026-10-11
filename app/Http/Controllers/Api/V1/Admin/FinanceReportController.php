<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\BankAccount;
use App\Models\BankLedgerEntry;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Services\LedgerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class FinanceReportController extends Controller
{
    public function summary(LedgerService $ledger): JsonResponse
    {
        $accounts = BankAccount::query()->with(['accountType', 'branch'])->get();
        $totalBalance = $accounts->sum(fn (BankAccount $account) => $ledger->balance($account));

        return ApiResponse::success([
            'totalBalance' => (int) $totalBalance,
            'accounts' => $accounts->map(fn (BankAccount $account) => [
                'id' => (string) $account->id,
                'accountName' => $account->account_name,
                'bankName' => $account->bank_name,
                'balance' => $ledger->balance($account),
            ])->values(),
            'totalReceivable' => (int) Invoice::query()->sum('amount_due'),
            'totalPayable' => (int) Purchase::query()->sum('due'),
            'totalIncome' => (int) Income::query()->where('is_cancelled', false)->sum('amount'),
            'totalExpense' => (int) Expense::query()->sum('paid'),
        ]);
    }

    public function cashFlow(AdminIndexRequest $request): JsonResponse
    {
        $query = BankLedgerEntry::query();

        if ($request->filled('startDate')) {
            $query->whereDate('occurred_at', '>=', $request->date('startDate'));
        }

        if ($request->filled('endDate')) {
            $query->whereDate('occurred_at', '<=', $request->date('endDate'));
        }

        $credits = (int) (clone $query)->where('direction', 'credit')->sum('amount');
        $debits = (int) (clone $query)->where('direction', 'debit')->sum('amount');

        $byType = (clone $query)
            ->selectRaw('reference_type, direction, SUM(amount) as total')
            ->groupBy('reference_type', 'direction')
            ->get()
            ->map(fn ($row) => [
                'referenceType' => $row->reference_type,
                'direction' => $row->direction,
                'total' => (int) $row->total,
            ])->values();

        return ApiResponse::success([
            'totalInflow' => $credits,
            'totalOutflow' => $debits,
            'net' => $credits - $debits,
            'breakdown' => $byType,
        ]);
    }

    public function customerEntries(AdminIndexRequest $request): JsonResponse
    {
        $query = CustomerAccountEntry::query()->with('customer.user');

        $customerId = trim((string) $request->input('customerId', ''));
        if ($customerId !== '') {
            $query->where('customer_id', (int) $customerId);
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $entries = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($entries->map(fn (CustomerAccountEntry $entry) => [
            'id' => (string) $entry->id,
            'customerId' => (string) $entry->customer_id,
            'customer' => $entry->customer?->user?->name,
            'sourceType' => $entry->source_type,
            'sourceId' => $entry->source_id !== null ? (string) $entry->source_id : null,
            'amount' => (int) $entry->amount,
            'occurredAt' => $entry->occurred_at?->toIso8601String(),
            'note' => $entry->note,
        ])->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function customerBalance(string $customerId): JsonResponse
    {
        $customer = Customer::query()->with('user')->findOrFail((int) $customerId);

        $sales = (int) CustomerAccountEntry::query()
            ->where('customer_id', $customer->id)->where('source_type', 'sale')->sum('amount');
        $adjustments = (int) CustomerAccountEntry::query()
            ->where('customer_id', $customer->id)->where('source_type', 'adjustment')->sum('amount');
        $payments = (int) CustomerAccountEntry::query()
            ->where('customer_id', $customer->id)->where('source_type', 'payment')->sum('amount');

        return ApiResponse::success([
            'customerId' => (string) $customer->id,
            'customer' => $customer->user?->name,
            'totalSales' => $sales,
            'totalAdjustments' => $adjustments,
            'totalPayments' => $payments,
            'balance' => $sales + $adjustments - $payments,
        ]);
    }

    /**
     * Neraca (balance sheet) ringkas dari ledger, piutang, stok pembelian,
     * dan laba ditahan (pendapatan − beban).
     */
    public function balanceSheet(): JsonResponse
    {
        $cashAndBank = (int) BankAccount::query()->get()
            ->sum(fn ($account) => app(LedgerService::class)->balance($account));
        $receivables = (int) Invoice::query()->sum('amount_due');
        $inventory = (int) Purchase::query()->sum('amount');
        $payables = (int) Purchase::query()->sum('due');
        $incomeTotal = (int) Income::query()->where('is_cancelled', false)->sum('amount');
        $expenseTotal = (int) Expense::query()->sum('paid');

        $totalCurrentAssets = $cashAndBank + $receivables + $inventory;
        $totalAssets = $totalCurrentAssets;
        $totalLiabilities = $payables;
        $retainedEarnings = $incomeTotal - $expenseTotal;
        $totalEquity = $retainedEarnings;
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquity;

        return ApiResponse::success([
            'asOfDate' => now()->toDateString(),
            'assets' => [
                'cashAndBank' => $cashAndBank,
                'receivables' => $receivables,
                'inventory' => $inventory,
                'machinery' => 0,
                'depreciation' => 0,
                'totalCurrentAssets' => $totalCurrentAssets,
                'totalFixedAssets' => 0,
                'totalAssets' => $totalAssets,
            ],
            'liabilities' => [
                'payables' => $payables,
                'accruedExpenses' => 0,
                'totalLiabilities' => $totalLiabilities,
            ],
            'equity' => [
                'ownerCapital' => 0,
                'retainedEarnings' => $retainedEarnings,
                'totalEquity' => $totalEquity,
            ],
            'totalLiabilitiesAndEquity' => $totalLiabilitiesAndEquity,
            'balanced' => $totalAssets === $totalLiabilitiesAndEquity,
            'notes' => ['Perhitungan ringkas dari ledger bank, piutang, stok pembelian, dan laba ditahan.'],
        ]);
    }
}
