<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\StoreBankAccountRequest;
use App\Http\Requests\Admin\StoreBankAccountTypeRequest;
use App\Http\Requests\Admin\UpdateBankAccountRequest;
use App\Http\Requests\Admin\UpdateBankAccountTypeRequest;
use App\Http\Resources\Admin\BankAccountResource;
use App\Http\Resources\Admin\BankAccountStatementResource;
use App\Http\Resources\Admin\BankAccountTypeResource;
use App\Http\Resources\Admin\BankLedgerEntryResource;
use App\Models\BankAccount;
use App\Models\BankAccountType;
use App\Services\BranchAccess;
use App\Services\LedgerService;
use App\Services\MasterDataService;
use App\Services\BankAccountService;
use App\Services\BankAccountTypeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BankAccountController extends Controller
{
    public function __construct(
        private readonly BankAccountService $accounts,
        private readonly BankAccountTypeService $types,
    ) {}

    public function types(): JsonResponse
    {
        return ApiResponse::success(BankAccountTypeResource::collection($this->types->list()));
    }

    public function storeType(StoreBankAccountTypeRequest $request): JsonResponse
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
        $type = $this->types->create($request->validated());

        return ApiResponse::success(new BankAccountTypeResource($type), [], 201);
    }

    public function updateType(UpdateBankAccountTypeRequest $request, BankAccountType $bankAccountType): JsonResponse
    {
        $type = $this->types->update($bankAccountType, $request->validated());

        return ApiResponse::success(new BankAccountTypeResource($type));
    }

    public function destroyType(BankAccountType $bankAccountType): JsonResponse
    {
        $this->types->delete($bankAccountType);

        return ApiResponse::success(['id' => (string) $bankAccountType->id, 'removed' => true]);
    }

    public function index(AdminIndexRequest $request): JsonResponse
    {
        $accounts = $this->accounts->list($request->input('search'));

        return ApiResponse::success(BankAccountResource::collection($accounts));
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
        $account = $this->accounts->create($request->validated());

        return ApiResponse::success(new BankAccountResource($account), [], 201);
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount): JsonResponse
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
        $account = $this->accounts->update($bankAccount, $request->validated());

        return ApiResponse::success(new BankAccountResource($account));
    }

    public function destroy(BankAccount $bankAccount): JsonResponse
    {
        MasterDataService::assertDeletable($bankAccount);
        $bankAccount->delete();
        $this->accounts->delete($bankAccount);

        return ApiResponse::success(['id' => (string) $bankAccount->id, 'removed' => true]);
    }

    public function ledger(BankAccount $bankAccount): JsonResponse
    {
        return ApiResponse::success(BankLedgerEntryResource::collection($this->accounts->ledger($bankAccount)));
    }

    public function statement(BankAccount $bankAccount): JsonResponse
    {
        return ApiResponse::success(new BankAccountStatementResource($this->accounts->statement($bankAccount)));
    }
}
