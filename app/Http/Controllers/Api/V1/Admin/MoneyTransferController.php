<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\StoreMoneyTransferRequest;
use App\Http\Resources\Admin\MoneyTransferResource;
use App\Models\BankAccount;
use App\Models\MoneyTransfer;
use App\Services\LedgerService;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MoneyTransferController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = MoneyTransfer::query()->with(['fromAccount', 'toAccount', 'createdBy']);

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('no', 'like', '%'.$search.'%');
        }

        if ($request->filled('startDate')) {
            $query->whereDate('occurred_at', '>=', $request->date('startDate'));
        }

        if ($request->filled('endDate')) {
            $query->whereDate('occurred_at', '<=', $request->date('endDate'));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $transfers = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(MoneyTransferResource::collection($transfers), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(StoreMoneyTransferRequest $request, LedgerService $ledger): JsonResponse
    {
        $validated = $request->validated();

        $transfer = DB::transaction(function () use ($request, $ledger, $validated): MoneyTransfer {
            $from = BankAccount::query()->findOrFail((int) $validated['fromAccountId']);
            $to = BankAccount::query()->findOrFail((int) $validated['toAccountId']);
            $occurredAt = $request->date('occurredAt') ?? now();

            $transfer = MoneyTransfer::query()->create([
                'no' => DocumentNumber::next('money_transfer', MoneyTransfer::query(), 'no', 3),
                'branch_id' => $validated['branchId'] ?? $from->branch_id,
                'from_account_id' => $from->id,
                'to_account_id' => $to->id,
                'amount' => (int) $validated['amount'],
                'description' => $validated['description'] ?? null,
                'occurred_at' => $occurredAt,
                'created_by' => $request->user()->id,
            ]);

            $ledger->record($from, 'debit', (int) $validated['amount'], 'transfer', $transfer->id, 'Transfer keluar '.$transfer->no, $occurredAt);
            $ledger->record($to, 'credit', (int) $validated['amount'], 'transfer', $transfer->id, 'Transfer masuk '.$transfer->no, $occurredAt);

            return $transfer->load(['fromAccount', 'toAccount', 'createdBy']);
        });

        return ApiResponse::success(new MoneyTransferResource($transfer), [], 201);
    }

    public function show(MoneyTransfer $moneyTransfer): JsonResponse
    {
        return ApiResponse::success(new MoneyTransferResource($moneyTransfer->load(['fromAccount', 'toAccount', 'createdBy'])));
    }
}
