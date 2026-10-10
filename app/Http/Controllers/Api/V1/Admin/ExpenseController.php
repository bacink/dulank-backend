<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Http\Requests\Admin\StoreExpenseRequest;
use App\Http\Resources\Admin\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = Expense::query()->with(['category', 'bankAccount']);

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $category = trim((string) $request->input('category', ''));
        if ($category !== '') {
            $query->whereHas('category', fn (Builder $query) => $query->where('name', $category));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query->where('name', 'like', $term)->orWhere('no_expense', 'like', $term));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $expenses = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(ExpenseResource::collection($expenses), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(StoreExpenseRequest $request, PaymentService $payments): JsonResponse
    {
        $validated = $request->validated();

        $expense = DB::transaction(function () use ($request, $payments, $validated): Expense {
            $category = null;
            if (! empty($validated['category'])) {
                $category = ExpenseCategory::query()->firstOrCreate(['name' => trim($validated['category'])]);
            }

            $expense = Expense::query()->create([
                'no_expense' => DocumentNumber::next('expense', Expense::query(), 'no_expense', 3),
                'date' => $request->date('date')?->toDateString() ?? now()->toDateString(),
                'expense_category_id' => $category?->id,
                'name' => $validated['name'],
                'status' => 'Unpaid',
                'amount' => (int) $validated['amount'],
                'paid' => 0,
                'due' => (int) $validated['amount'],
                'description' => $validated['description'] ?? null,
                'payment_method' => $validated['paymentMethod'] ?? null,
                'bank_account_id' => $validated['bankAccountId'] ?? null,
            ]);

            $paid = (int) ($validated['paid'] ?? 0);
            if ($paid > 0) {
                $payments->record(
                    $request->user(),
                    $expense,
                    min($paid, (int) $expense->amount),
                    (string) ($validated['paymentMethod'] ?? 'Cash'),
                    $expense->date?->toDateString(),
                    null,
                    PaymentDirection::Out,
                    $expense->bank_account_id,
                );
            }

            return $expense->refresh()->load(['category', 'bankAccount']);
        });

        return ApiResponse::success(new ExpenseResource($expense), [], 201);
    }

    public function payments(RecordPaymentRequest $request, Expense $expense, PaymentService $payments): JsonResponse
    {
        $amount = $request->integer('amount');

        if ($amount > (int) $expense->due) {
            return ApiResponse::error('Nominal melebihi sisa utang.', 422, [
                'amount' => ['Nominal melebihi sisa utang (maksimal '.number_format((int) $expense->due, 0, ',', '.').').'],
            ]);
        }

        $payments->record(
            $request->user(),
            $expense,
            $amount,
            (string) ($request->input('method') ?? 'Transfer'),
            $request->date('date')?->toDateString(),
            $request->input('notes'),
            PaymentDirection::Out,
            $expense->bank_account_id,
        );

        return ApiResponse::success(new ExpenseResource($expense->refresh()->load(['category', 'bankAccount'])), [], 201);
    }

    public function update(StoreExpenseRequest $request, Expense $expense): JsonResponse
    {
        $validated = $request->validated();

        $category = null;
        if (! empty($validated['category'])) {
            $category = ExpenseCategory::query()->firstOrCreate(['name' => trim($validated['category'])]);
        }

        $expense->forceFill([
            'date' => $request->date('date')?->toDateString() ?? $expense->date?->toDateString(),
            'expense_category_id' => $category?->id ?? $expense->expense_category_id,
            'name' => $validated['name'],
            'amount' => (int) $validated['amount'],
            'due' => max((int) $validated['amount'] - (int) $expense->paid, 0),
            'description' => $validated['description'] ?? null,
            'payment_method' => $validated['paymentMethod'] ?? null,
            'bank_account_id' => $validated['bankAccountId'] ?? $expense->bank_account_id,
        ])->save();

        return ApiResponse::success(new ExpenseResource($expense->refresh()->load(['category', 'bankAccount'])));
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $expense->delete();

        return ApiResponse::success(['id' => (string) $expense->id, 'removed' => true]);
    }

    public function categories(): JsonResponse
    {
        return ApiResponse::success(ExpenseCategory::query()->orderBy('name')->get()->map(fn (ExpenseCategory $category) => [
            'id' => (string) $category->id,
            'name' => $category->name,
            'status' => $category->status,
        ]));
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $category = ExpenseCategory::query()->create($validated);

        return ApiResponse::success(['id' => (string) $category->id, 'name' => $category->name], [], 201);
    }
}
