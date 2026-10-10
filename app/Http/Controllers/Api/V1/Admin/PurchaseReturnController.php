<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Http\Requests\Admin\StorePurchaseReturnRequest;
use App\Http\Resources\Admin\PurchaseReturnResource;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PurchaseReturnController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = PurchaseReturn::query()->with(['supplier', 'items']);

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('no_pr', 'like', $term)
                    ->orWhere('no_purchase', 'like', $term)
                    ->orWhereHas('supplier', fn (Builder $query) => $query->where('name', 'like', $term));
            });
        }

        if ($request->filled('startDate')) {
            $query->whereDate('date', '>=', $request->date('startDate'));
        }

        if ($request->filled('endDate')) {
            $query->whereDate('date', '<=', $request->date('endDate'));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $returns = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(PurchaseReturnResource::collection($returns), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(StorePurchaseReturnRequest $request, PaymentService $payments): JsonResponse
    {
        $return = DB::transaction(function () use ($request, $payments): PurchaseReturn {
            $validated = $request->validated();
            $items = $this->buildItems($validated['items']);
            $amount = (int) collect($items)->sum('amount');

            $return = PurchaseReturn::query()->create([
                'no_pr' => $validated['noPR'] ?? DocumentNumber::next('purchase_return', PurchaseReturn::query(), 'no_pr', 3),
                'no_purchase' => $validated['noPurchase'],
                'supplier_id' => $this->resolveSupplier($validated['supplier'])->id,
                'date' => $request->date('date')?->toDateString() ?? now()->toDateString(),
                'amount' => $amount,
                'paid' => 0,
                'due' => $amount,
                'status' => $validated['status'] ?? 'Pending',
                'status_by' => $validated['statusBy'] ?? $request->user()->name,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $return->items()->create($item);
            }

            $paid = (int) ($validated['paid'] ?? 0);
            if ($paid > 0) {
                $payments->record(
                    $request->user(),
                    $return,
                    min($paid, $amount),
                    'Transfer',
                    $return->date?->toDateString(),
                    null,
                    PaymentDirection::In,
                );
            }

            return $return->refresh()->load(['supplier', 'items']);
        });

        return ApiResponse::success(new PurchaseReturnResource($return), [], 201);
    }

    public function show(string $noPr): JsonResponse
    {
        $return = PurchaseReturn::query()
            ->where('no_pr', $noPr)
            ->with(['supplier', 'items', 'refundPayment'])
            ->firstOrFail();

        return ApiResponse::success(new PurchaseReturnResource($return));
    }

    public function update(StorePurchaseReturnRequest $request, string $noPr): JsonResponse
    {
        $return = PurchaseReturn::query()->where('no_pr', $noPr)->firstOrFail();

        DB::transaction(function () use ($request, $return): void {
            $validated = $request->validated();
            $items = $this->buildItems($validated['items']);
            $amount = (int) collect($items)->sum('amount');

            $return->forceFill([
                'no_purchase' => $validated['noPurchase'],
                'supplier_id' => $this->resolveSupplier($validated['supplier'])->id,
                'date' => $request->date('date')?->toDateString() ?? $return->date?->toDateString(),
                'amount' => $amount,
                'due' => max($amount - (int) $return->paid, 0),
                'status' => $validated['status'] ?? $return->status,
                'status_by' => $validated['statusBy'] ?? $request->user()->name,
                'notes' => $validated['notes'] ?? null,
            ])->save();

            $return->items()->delete();
            foreach ($items as $item) {
                $return->items()->create($item);
            }
        });

        return ApiResponse::success(new PurchaseReturnResource($return->refresh()->load(['supplier', 'items'])));
    }

    public function destroy(string $noPr): JsonResponse
    {
        $return = PurchaseReturn::query()->where('no_pr', $noPr)->firstOrFail();
        $return->delete();

        return ApiResponse::success(['id' => $noPr, 'removed' => true]);
    }

    /**
     * Penerimaan refund dari supplier (inflow).
     */
    public function recordPayment(RecordPaymentRequest $request, string $noPr, PaymentService $payments): JsonResponse
    {
        $return = PurchaseReturn::query()->where('no_pr', $noPr)->firstOrFail();
        $amount = $request->integer('amount');

        if ($amount > (int) $return->due) {
            return ApiResponse::error('Nominal melebihi sisa retur.', 422, [
                'amount' => ['Nominal melebihi sisa retur (maksimal '.number_format((int) $return->due, 0, ',', '.').').'],
            ]);
        }

        $payments->record(
            $request->user(),
            $return,
            $amount,
            (string) ($request->input('method') ?? 'Transfer'),
            $request->date('date')?->toDateString(),
            $request->input('notes'),
            PaymentDirection::In,
        );

        return ApiResponse::success(new PurchaseReturnResource($return->refresh()->load(['supplier', 'items'])), [], 201);
    }

    private function resolveSupplier(string $name): Supplier
    {
        return Supplier::query()->firstOrCreate(
            ['name' => trim($name)],
            [
                'code' => DocumentNumber::next('supplier', Supplier::query(), 'code', 3),
                'status' => 'Active',
                'date' => now()->toDateString(),
            ],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildItems(array $items): array
    {
        return array_map(function (array $item): array {
            $returnQty = (float) $item['returnQty'];
            $price = (int) $item['price'];

            return [
                'name' => (string) $item['name'],
                'qty' => (float) $item['qty'],
                'return_qty' => $returnQty,
                'unit' => $item['unit'] ?? null,
                'price' => $price,
                'amount' => (int) ($item['amount'] ?? round($price * $returnQty)),
                'reason' => $item['reason'] ?? null,
            ];
        }, $items);
    }
}
