<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Http\Requests\Admin\StorePurchaseRequest;
use App\Http\Resources\Admin\PurchaseResource;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = Purchase::query()->with(['supplier', 'lines']);

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $paymentStatus = trim((string) $request->input('paymentStatus', ''));
        if ($paymentStatus !== '') {
            $query->where('payment_status', $paymentStatus);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('no_purchase', 'like', $term)
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
        $purchases = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(PurchaseResource::collection($purchases), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(StorePurchaseRequest $request, PaymentService $payments): JsonResponse
    {
        $purchase = DB::transaction(function () use ($request, $payments): Purchase {
            $validated = $request->validated();
            $lines = $this->buildLines($validated['items']);
            $shipping = (int) ($validated['shippingCost'] ?? 0);
            $tax = (int) ($validated['tax'] ?? 0);
            $amount = (int) collect($lines)->sum('total') + $shipping + $tax;

            $purchase = Purchase::query()->create([
                'no_purchase' => $validated['noPurchase'] ?? DocumentNumber::next('goods_receipt', Purchase::query(), 'no_purchase', 3),
                'supplier_id' => $this->resolveSupplier($validated['supplier'])->id,
                'date' => $request->date('date')?->toDateString() ?? now()->toDateString(),
                'status' => $validated['status'] ?? 'Pending',
                'amount' => $amount,
                'paid' => 0,
                'due' => $amount,
                'payment_status' => 'Unpaid',
                'shipping_cost' => $shipping,
                'tax' => $tax,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $purchase->lines()->create($line);
            }

            $paid = (int) ($validated['paid'] ?? 0);
            if ($paid > 0) {
                $payments->record(
                    $request->user(),
                    $purchase,
                    min($paid, $amount),
                    'Transfer',
                    $purchase->date?->toDateString(),
                    null,
                    PaymentDirection::Out,
                );
            }

            return $purchase->refresh()->load(['supplier', 'lines']);
        });

        return ApiResponse::success(new PurchaseResource($purchase), [], 201);
    }

    public function show(string $noPurchase): JsonResponse
    {
        $purchase = Purchase::query()
            ->where('no_purchase', $noPurchase)
            ->with(['supplier', 'lines', 'payments'])
            ->firstOrFail();

        return ApiResponse::success(new PurchaseResource($purchase));
    }

    public function update(StorePurchaseRequest $request, string $noPurchase): JsonResponse
    {
        $purchase = Purchase::query()->where('no_purchase', $noPurchase)->firstOrFail();

        DB::transaction(function () use ($request, $purchase): void {
            $validated = $request->validated();
            $lines = $this->buildLines($validated['items']);
            $shipping = (int) ($validated['shippingCost'] ?? 0);
            $tax = (int) ($validated['tax'] ?? 0);
            $amount = (int) collect($lines)->sum('total') + $shipping + $tax;

            $purchase->forceFill([
                'supplier_id' => $this->resolveSupplier($validated['supplier'])->id,
                'date' => $request->date('date')?->toDateString() ?? $purchase->date?->toDateString(),
                'status' => $validated['status'] ?? $purchase->status,
                'amount' => $amount,
                'due' => max($amount - (int) $purchase->paid, 0),
                'shipping_cost' => $shipping,
                'tax' => $tax,
                'notes' => $validated['notes'] ?? null,
            ])->save();

            $purchase->lines()->delete();
            foreach ($lines as $line) {
                $purchase->lines()->create($line);
            }
        });

        return ApiResponse::success(new PurchaseResource($purchase->refresh()->load(['supplier', 'lines'])));
    }

    public function destroy(string $noPurchase): JsonResponse
    {
        $purchase = Purchase::query()->where('no_purchase', $noPurchase)->firstOrFail();
        $purchase->delete();

        return ApiResponse::success(['id' => $noPurchase, 'removed' => true]);
    }

    /**
     * Pembayaran utang pembelian (outflow ke supplier).
     */
    public function recordPayment(RecordPaymentRequest $request, string $noPurchase, PaymentService $payments): JsonResponse
    {
        $purchase = Purchase::query()->where('no_purchase', $noPurchase)->firstOrFail();
        $amount = $request->integer('amount');

        if ($amount > (int) $purchase->due) {
            return ApiResponse::error('Nominal melebihi sisa utang.', 422, [
                'amount' => ['Nominal melebihi sisa utang (maksimal '.number_format((int) $purchase->due, 0, ',', '.').').'],
            ]);
        }

        $payments->record(
            $request->user(),
            $purchase,
            $amount,
            (string) ($request->input('method') ?? 'Transfer'),
            $request->date('date')?->toDateString(),
            $request->input('notes'),
            PaymentDirection::Out,
        );

        return ApiResponse::success(new PurchaseResource($purchase->refresh()->load(['supplier', 'lines'])), [], 201);
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
    private function buildLines(array $items): array
    {
        return array_map(function (array $item): array {
            $qty = (float) $item['qty'];
            $price = (int) $item['price'];

            return [
                'name' => (string) $item['name'],
                'qty' => $qty,
                'unit' => $item['unit'] ?? null,
                'price' => $price,
                'total' => (int) round($price * $qty),
            ];
        }, $items);
    }
}
