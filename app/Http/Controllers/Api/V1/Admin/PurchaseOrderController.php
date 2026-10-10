<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\StorePurchaseOrderRequest;
use App\Http\Resources\Admin\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = PurchaseOrder::query()->with(['supplier', 'items']);

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('po_status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('no_po', 'like', $term)
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
        $orders = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(PurchaseOrderResource::collection($orders), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $order = DB::transaction(function () use ($request): PurchaseOrder {
            $validated = $request->validated();
            $items = $this->buildItems($validated['items']);
            $amount = (int) ($validated['amount'] ?? collect($items)->sum('total'));

            $order = PurchaseOrder::query()->create([
                'no_po' => $validated['noPO'] ?? DocumentNumber::next('purchase', PurchaseOrder::query(), 'no_po', 3),
                'date' => $request->date('date')?->toDateString() ?? now()->toDateString(),
                'created_by_name' => $request->user()->name,
                'no_purchase' => $validated['noPurchase'] ?? null,
                'supplier_id' => $this->resolveSupplier($validated['supplier'])->id,
                'amount' => $amount,
                'po_status' => $validated['poStatus'] ?? 'Draft',
                'goods_status' => $validated['goodsStatus'] ?? 'Pending',
                'goods_date' => $request->date('goodsDate')?->toDateString(),
                'goods_by' => $validated['goodsBy'] ?? null,
                'term_of_payment' => $validated['termOfPayment'] ?? null,
                'delivery_date' => $request->date('deliveryDate')?->toDateString(),
                'delivery_address' => $validated['deliveryAddress'] ?? null,
                'vendor_reff' => $validated['vendorReff'] ?? null,
                'tax_rate' => (float) ($validated['taxRate'] ?? 0),
            ]);

            foreach ($items as $item) {
                $order->items()->create($item);
            }

            return $order->load(['supplier', 'items']);
        });

        return ApiResponse::success(new PurchaseOrderResource($order), [], 201);
    }

    public function show(string $noPo): JsonResponse
    {
        $order = PurchaseOrder::query()
            ->where('no_po', $noPo)
            ->with(['supplier', 'items'])
            ->firstOrFail();

        return ApiResponse::success(new PurchaseOrderResource($order));
    }

    public function update(StorePurchaseOrderRequest $request, string $noPo): JsonResponse
    {
        $order = PurchaseOrder::query()->where('no_po', $noPo)->firstOrFail();

        DB::transaction(function () use ($request, $order): void {
            $validated = $request->validated();
            $items = $this->buildItems($validated['items']);
            $amount = (int) ($validated['amount'] ?? collect($items)->sum('total'));

            $order->forceFill([
                'date' => $request->date('date')?->toDateString() ?? $order->date?->toDateString(),
                'no_purchase' => $validated['noPurchase'] ?? $order->no_purchase,
                'supplier_id' => $this->resolveSupplier($validated['supplier'])->id,
                'amount' => $amount,
                'po_status' => $validated['poStatus'] ?? $order->po_status,
                'goods_status' => $validated['goodsStatus'] ?? $order->goods_status,
                'goods_date' => $request->date('goodsDate')?->toDateString() ?? $order->goods_date?->toDateString(),
                'goods_by' => $validated['goodsBy'] ?? $order->goods_by,
                'term_of_payment' => $validated['termOfPayment'] ?? $order->term_of_payment,
                'delivery_date' => $request->date('deliveryDate')?->toDateString() ?? $order->delivery_date?->toDateString(),
                'delivery_address' => $validated['deliveryAddress'] ?? $order->delivery_address,
                'vendor_reff' => $validated['vendorReff'] ?? $order->vendor_reff,
                'tax_rate' => (float) ($validated['taxRate'] ?? $order->tax_rate),
            ])->save();

            $order->items()->delete();
            foreach ($items as $item) {
                $order->items()->create($item);
            }
        });

        return ApiResponse::success(new PurchaseOrderResource($order->refresh()->load(['supplier', 'items'])));
    }

    public function destroy(string $noPo): JsonResponse
    {
        $order = PurchaseOrder::query()->where('no_po', $noPo)->firstOrFail();
        $order->delete();

        return ApiResponse::success(['id' => $noPo, 'removed' => true]);
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
