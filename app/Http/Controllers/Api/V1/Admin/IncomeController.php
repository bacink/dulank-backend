<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\StoreIncomeRequest;
use App\Http\Resources\Admin\IncomeResource;
use App\Models\BankAccount;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Services\LedgerService;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncomeController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = Income::query()->with(['category', 'bankAccount']);

        $category = trim((string) $request->input('category', ''));
        if ($category !== '') {
            $query->whereHas('category', fn (Builder $query) => $query->where('name', $category));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('no', 'like', $term));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $incomes = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(IncomeResource::collection($incomes), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(StoreIncomeRequest $request, LedgerService $ledger): JsonResponse
    {
        $validated = $request->validated();

        $income = DB::transaction(function () use ($request, $ledger, $validated): Income {
            $category = null;
            if (! empty($validated['category'])) {
                $category = IncomeCategory::query()->firstOrCreate(['name' => trim($validated['category'])]);
            }

            $income = Income::query()->create([
                'date' => $request->date('date')?->toDateString() ?? now()->toDateString(),
                'no' => DocumentNumber::next('income', Income::query(), 'no', 3),
                'name' => $validated['name'],
                'income_category_id' => $category?->id,
                'notes' => $validated['notes'] ?? null,
                'amount' => (int) $validated['amount'],
                'payment_method' => $validated['paymentMethod'] ?? null,
                'bank_account_id' => $validated['bankAccountId'] ?? null,
                'is_cancelled' => false,
            ]);

            if ($income->bank_account_id !== null) {
                $account = BankAccount::query()->find($income->bank_account_id);
                if ($account !== null) {
                    $ledger->record($account, 'credit', (int) $income->amount, 'income', $income->id, 'Pendapatan '.$income->no, $income->date?->startOfDay());
                }
            }

            return $income->load(['category', 'bankAccount']);
        });

        return ApiResponse::success(new IncomeResource($income), [], 201);
    }

    public function update(StoreIncomeRequest $request, Income $income): JsonResponse
    {
        $validated = $request->validated();

        $category = null;
        if (! empty($validated['category'])) {
            $category = IncomeCategory::query()->firstOrCreate(['name' => trim($validated['category'])]);
        }

        $income->forceFill([
            'date' => $request->date('date')?->toDateString() ?? $income->date?->toDateString(),
            'name' => $validated['name'],
            'income_category_id' => $category?->id ?? $income->income_category_id,
            'notes' => $validated['notes'] ?? null,
            'amount' => (int) $validated['amount'],
            'payment_method' => $validated['paymentMethod'] ?? null,
            'bank_account_id' => $validated['bankAccountId'] ?? null,
        ])->save();

        return ApiResponse::success(new IncomeResource($income->refresh()->load(['category', 'bankAccount'])));
    }

    public function cancel(Income $income, LedgerService $ledger): JsonResponse
    {
        if ($income->is_cancelled) {
            return ApiResponse::error('Pendapatan sudah dibatalkan.', 409);
        }

        DB::transaction(function () use ($income, $ledger): void {
            $income->forceFill(['is_cancelled' => true])->save();

            if ($income->bank_account_id !== null) {
                $account = BankAccount::query()->find($income->bank_account_id);
                if ($account !== null) {
                    $ledger->record($account, 'debit', (int) $income->amount, 'income_cancel', $income->id, 'Pembatalan pendapatan '.$income->no);
                }
            }
        });

        return ApiResponse::success(new IncomeResource($income->refresh()->load(['category', 'bankAccount'])));
    }

    public function destroy(Income $income): JsonResponse
    {
        $income->delete();

        return ApiResponse::success(['id' => (string) $income->id, 'removed' => true]);
    }

    public function categories(): JsonResponse
    {
        return ApiResponse::success(IncomeCategory::query()->orderBy('name')->get()->map(fn (IncomeCategory $category) => [
            'id' => (string) $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'status' => $category->status,
        ]));
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $category = IncomeCategory::query()->create($validated);

        return ApiResponse::success(['id' => (string) $category->id, 'name' => $category->name], [], 201);
    }
}
