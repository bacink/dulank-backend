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
        $account = $this->accounts->create($request->validated());

        return ApiResponse::success(new BankAccountResource($account), [], 201);
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount): JsonResponse
    {
        $account = $this->accounts->update($bankAccount, $request->validated());

        return ApiResponse::success(new BankAccountResource($account));
    }

    public function destroy(BankAccount $bankAccount): JsonResponse
    {
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
