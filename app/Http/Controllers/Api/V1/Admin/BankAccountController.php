<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBankAccountRequest;
use App\Http\Resources\Admin\BankAccountResource;
use App\Http\Resources\Admin\BankAccountTypeResource;
use App\Http\Resources\Admin\BankLedgerEntryResource;
use App\Models\BankAccount;
use App\Models\BankAccountType;
use App\Services\BranchAccess;
use App\Services\LedgerService;
use App\Services\MasterDataService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function types(): JsonResponse
    {
        $types = BankAccountType::query()->withCount('accounts')->orderBy('name')->get();

        return ApiResponse::success(BankAccountTypeResource::collection($types));
    }

    public function storeType(Request $request): JsonResponse
    {
        if ($id = $request->input('id')) {
            $bankAccountType = BankAccountType::query()->findOrFail($id);

            return $this->updateType($request, $bankAccountType);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $type = BankAccountType::query()->create([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success(new BankAccountTypeResource($type), [], 201);
    }

    public function updateType(Request $request, BankAccountType $bankAccountType): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $bankAccountType->forceFill([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? $bankAccountType->status,
        ])->save();

        return ApiResponse::success(new BankAccountTypeResource($bankAccountType->refresh()));
    }

    public function destroyType(BankAccountType $bankAccountType): JsonResponse
    {
        $bankAccountType->delete();

        return ApiResponse::success(['id' => (string) $bankAccountType->id, 'removed' => true]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = BankAccount::query()->with(['accountType', 'branch']);

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('account_name', 'like', '%'.$search.'%')
                    ->orWhere('bank_name', 'like', '%'.$search.'%')
                    ->orWhere('account_no', 'like', '%'.$search.'%');
            });
        }

        return ApiResponse::success(BankAccountResource::collection($query->orderBy('account_name')->get()));
    }

    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        if ($id = $request->input('id')) {
            $bankAccount = BankAccount::query()->findOrFail($id);

            return $this->update($request, $bankAccount);
        }

        $validated = $request->validated();
        BranchAccess::assertAllowed($validated['branchId'] ?? null);

        $account = BankAccount::query()->create([
            'branch_id' => $validated['branchId'] ?? null,
            'bank_account_type_id' => $validated['accountTypeId'] ?? null,
            'account_name' => $validated['accountName'],
            'bank_name' => $validated['bankName'] ?? null,
            'account_no' => $validated['accountNo'] ?? null,
            'opening_balance' => (int) ($validated['openingBalance'] ?? 0),
            'ifsc' => $validated['ifsc'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success(new BankAccountResource($account->load(['accountType', 'branch'])), [], 201);
    }

    public function update(StoreBankAccountRequest $request, BankAccount $bankAccount): JsonResponse
    {
        $validated = $request->validated();
        BranchAccess::assertAllowed($validated['branchId'] ?? $bankAccount->branch_id);
        if (isset($validated['openingBalance']) && (int) $validated['openingBalance'] !== (int) $bankAccount->opening_balance && $bankAccount->ledgerEntries()->exists()) {
            return ApiResponse::error('Saldo awal tidak dapat diubah setelah ada mutasi.', 422);
        }

        $bankAccount->forceFill([
            'branch_id' => $validated['branchId'] ?? $bankAccount->branch_id,
            'bank_account_type_id' => $validated['accountTypeId'] ?? $bankAccount->bank_account_type_id,
            'account_name' => $validated['accountName'],
            'bank_name' => $validated['bankName'] ?? null,
            'account_no' => $validated['accountNo'] ?? null,
            'opening_balance' => (int) ($validated['openingBalance'] ?? $bankAccount->opening_balance),
            'ifsc' => $validated['ifsc'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? $bankAccount->status,
        ])->save();

        return ApiResponse::success(new BankAccountResource($bankAccount->refresh()->load(['accountType', 'branch'])));
    }

    public function destroy(BankAccount $bankAccount): JsonResponse
    {
        MasterDataService::assertDeletable($bankAccount);
        $bankAccount->delete();

        return ApiResponse::success(['id' => (string) $bankAccount->id, 'removed' => true]);
    }

    public function ledger(BankAccount $bankAccount): JsonResponse
    {
        $entries = $bankAccount->ledgerEntries()->orderByDesc('id')->limit(200)->get();

        return ApiResponse::success(BankLedgerEntryResource::collection($entries));
    }

    public function statement(BankAccount $bankAccount, LedgerService $ledger): JsonResponse
    {
        $credit = (int) $bankAccount->ledgerEntries()->where('direction', 'credit')->sum('amount');
        $debit = (int) $bankAccount->ledgerEntries()->where('direction', 'debit')->sum('amount');

        return ApiResponse::success([
            'account' => new BankAccountResource($bankAccount->load(['accountType', 'branch'])),
            'openingBalance' => (int) $bankAccount->opening_balance,
            'totalCredit' => $credit,
            'totalDebit' => $debit,
            'currentBalance' => $ledger->balance($bankAccount),
            'entries' => BankLedgerEntryResource::collection(
                $bankAccount->ledgerEntries()->orderByDesc('id')->limit(200)->get()
            ),
        ]);
    }
}
