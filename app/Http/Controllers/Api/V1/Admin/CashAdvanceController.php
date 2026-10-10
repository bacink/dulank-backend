<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\StoreCashAdvanceRequest;
use App\Http\Resources\Admin\CashAdvanceResource;
use App\Models\CashAdvance;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashAdvanceController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = CashAdvance::query()->with('payments');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('employee_name', 'like', '%'.$search.'%');
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $advances = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(CashAdvanceResource::collection($advances), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(StoreCashAdvanceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $advance = CashAdvance::query()->create([
            'employee_id' => $validated['employeeId'] ?? null,
            'employee_name' => $validated['employeeName'] ?? null,
            'bank_account_id' => $validated['bankAccountId'] ?? null,
            'date' => $request->date('date')?->toDateString() ?? now()->toDateString(),
            'total_cash' => (int) $validated['totalCash'],
            'installment_count' => (int) ($validated['installmentCount'] ?? 1),
            'period' => $validated['period'] ?? 'Monthly',
            'note' => $validated['note'] ?? null,
        ]);

        return ApiResponse::success(new CashAdvanceResource($advance->load('payments')), [], 201);
    }

    public function show(CashAdvance $cashAdvance): JsonResponse
    {
        return ApiResponse::success(new CashAdvanceResource($cashAdvance->load('payments')));
    }

    public function recordPayment(Request $request, CashAdvance $cashAdvance): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'date' => ['sometimes', 'nullable', 'date'],
            'referenceId' => ['sometimes', 'nullable', 'string', 'max:60'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $paid = (int) $cashAdvance->payments()->sum('amount');
        $outstanding = max((int) $cashAdvance->total_cash - $paid, 0);

        if ((int) $validated['amount'] > $outstanding) {
            return ApiResponse::error('Nominal melebihi sisa kas bon.', 422, [
                'amount' => ['Nominal melebihi sisa kas bon (maksimal '.number_format($outstanding, 0, ',', '.').').'],
            ]);
        }

        $cashAdvance->payments()->create([
            'date' => $request->date('date')?->toDateString() ?? now()->toDateString(),
            'amount' => (int) $validated['amount'],
            'reference_id' => $validated['referenceId'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        return ApiResponse::success(new CashAdvanceResource($cashAdvance->refresh()->load('payments')), [], 201);
    }

    public function destroy(CashAdvance $cashAdvance): JsonResponse
    {
        $cashAdvance->delete();

        return ApiResponse::success(['id' => (string) $cashAdvance->id, 'removed' => true]);
    }
}
