<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Http\Requests\Admin\RefundSalesReturnRequest;
use App\Http\Requests\Admin\StoreSalesReturnRequest;
use App\Http\Requests\Admin\UpdateSalesReturnRequest;
use App\Http\Resources\Admin\SalesReturnResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SalesReturn;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SalesReturnController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = SalesReturn::query()->with(['items', 'order', 'refundPayment']);

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('payment_status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('return_no', 'like', $term)->orWhere('customer', 'like', $term);
            });
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $returns = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(SalesReturnResource::collection($returns), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(StoreSalesReturnRequest $request): JsonResponse
    {
        $return = DB::transaction(function () use ($request): SalesReturn {
            $validated = $request->validated();
            $order = $this->resolveOrder($validated['salesNo'] ?? null);
            $items = $this->buildItems($validated['items']);

            $return = SalesReturn::query()->create([
                'return_no' => DocumentNumber::next('sales_return', SalesReturn::query(), 'return_no', 3),
                'order_id' => $order?->id,
                'date' => $request->date('date')?->toDateString() ?? now()->toDateString(),
                'customer' => $validated['customer'] ?? $order?->contact['name'] ?? null,
                'payment_status' => 'Unpaid',
                'total' => (int) collect($items)->sum('return_amount'),
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $return->items()->create($item);
            }

            return $return->load(['items', 'order', 'refundPayment']);
        });

        return ApiResponse::success(new SalesReturnResource($return), [], 201);
    }

    public function show(string $returnNo): JsonResponse
    {
        $return = SalesReturn::query()
            ->where('return_no', $returnNo)
            ->with(['items', 'order', 'refundPayment'])
            ->firstOrFail();

        return ApiResponse::success(new SalesReturnResource($return));
    }

    public function update(UpdateSalesReturnRequest $request, string $returnNo): JsonResponse
    {
        $return = SalesReturn::query()->where('return_no', $returnNo)->firstOrFail();

        if ($return->payment_status === 'Paid') {
            return ApiResponse::error('Retur yang sudah dibayar tidak dapat diubah.', 422);
        }

        DB::transaction(function () use ($request, $return): void {
            $validated = $request->validated();
            $items = $this->buildItems($validated['items']);

            $return->forceFill([
                'date' => $request->date('date')?->toDateString() ?? $return->date?->toDateString(),
                'customer' => $validated['customer'] ?? $return->customer,
                'notes' => $validated['notes'] ?? $return->notes,
                'total' => (int) collect($items)->sum('return_amount'),
            ])->save();

            $return->items()->delete();
            foreach ($items as $item) {
                $return->items()->create($item);
            }
        });

        return ApiResponse::success(new SalesReturnResource($return->refresh()->load(['items', 'order', 'refundPayment'])));
    }

    public function destroy(string $returnNo): JsonResponse
    {
        $return = SalesReturn::query()->where('return_no', $returnNo)->firstOrFail();
        $return->delete();

        return ApiResponse::success(['id' => $returnNo, 'removed' => true]);
    }

    public function refund(RefundSalesReturnRequest $request, string $returnNo): JsonResponse
    {
        $return = SalesReturn::query()->where('return_no', $returnNo)->with('order')->firstOrFail();

        if ($return->payment_status === 'Paid') {
            return ApiResponse::error('Retur sudah dibayar sebelumnya.', 409);
        }

        $amount = (int) ($request->input('amount') ?? $return->total);

        DB::transaction(function () use ($request, $return, $amount): void {
            Payment::query()->create([
                'payable_type' => SalesReturn::class,
                'payable_id' => $return->id,
                'direction' => 'out',
                'ref_no' => DocumentNumber::next('payment', Payment::query(), 'ref_no', 5),
                'user_id' => $return->order?->user_id,
                'date' => $request->date('date')?->toDateString() ?? now()->toDateString(),
                'amount' => $amount,
                'method' => $request->string('method')->toString(),
                'status' => 'Verified',
                'verified_at' => now(),
                'verified_by' => $request->user()->id,
                'notes' => $request->input('reference') ?? $request->input('notes'),
            ]);

            $return->forceFill([
                'payment_status' => 'Paid',
                'payment_date' => now()->toDateString(),
                'payment_method' => $request->string('method')->toString(),
            ])->save();

            $return->order?->statusHistories()->create([
                'status' => 'return-refunded',
                'note' => 'Refund retur '.$return->return_no.' sebesar '.number_format($amount, 0, ',', '.'),
                'occurred_at' => now(),
                'created_by' => $request->user()->id,
            ]);
        });

        return ApiResponse::success(new SalesReturnResource($return->refresh()->load(['items', 'order', 'refundPayment'])));
    }

    private function resolveOrder(?string $salesNo): ?Order
    {
        if ($salesNo === null || trim($salesNo) === '') {
            return null;
        }

        return Order::query()->where('number', $salesNo)->first();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildItems(array $items): array
    {
        return array_map(function (array $item): array {
            $price = (int) ($item['price'] ?? 0);
            $qtyReturn = (float) $item['qtyReturn'];

            return [
                'name' => (string) $item['name'],
                'description' => $item['description'] ?? null,
                'qty_order' => (float) ($item['qtyOrder'] ?? 0),
                'qty_return' => $qtyReturn,
                'unit' => $item['unit'] ?? null,
                'price' => $price,
                'return_amount' => (int) ($item['returnAmount'] ?? round($price * $qtyReturn)),
                'reason' => $item['reason'] ?? null,
            ];
        }, $items);
    }
}
