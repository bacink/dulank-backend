<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShippingMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderIndexRequest;
use App\Http\Requests\Admin\RecordPaymentRequest;
use App\Http\Requests\Admin\StoreSaleRequest;
use App\Http\Resources\Admin\SaleResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\PaymentService;
use App\Services\TaxService;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index(OrderIndexRequest $request): JsonResponse
    {
        $query = Order::query()->with(['items', 'payments']);

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('payment_status', $status);
        }

        $channel = trim((string) $request->input('channel', ''));
        if ($channel !== '') {
            $query->where('channel', strtolower($channel));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('number', 'like', $term)->orWhere('contact->name', 'like', $term);
            });
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $sales = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(SaleResource::collection($sales), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(StoreSaleRequest $request, TaxService $tax, PaymentService $payments): JsonResponse
    {
        $validated = $request->validated();

        $order = DB::transaction(function () use ($request, $tax, $payments, $validated) {
            $lines = $this->buildLines($validated['items']);
            $subTotal = (int) collect($lines)->sum('total');
            $discount = (int) ($validated['discount'] ?? 0);
            $deliveryFee = (int) ($validated['deliveryFee'] ?? 0);
            $taxAmount = $tax->calculate(max($subTotal - $discount, 0));
            $total = max($subTotal - $discount, 0) + $taxAmount + $deliveryFee;

            $paidAmount = (int) ($validated['paidAmount'] ?? 0);
            $fullyPaid = $paidAmount >= $total && $total > 0;

            $order = Order::query()->create([
                'number' => DocumentNumber::next('order', Order::query(), 'number', 3),
                'channel' => OrderChannel::Pos,
                'status' => $fullyPaid ? OrderStatus::Delivered : OrderStatus::AwaitingPayment,
                'status_by' => $request->user()->name,
                'shipping_method' => isset($validated['delivery'])
                    ? ShippingMethod::fromAdmin((string) $validated['delivery'])
                    : ShippingMethod::PickUp,
                'contact' => ['name' => $validated['customer']],
                'sub_total' => $subTotal,
                'discount' => $discount,
                'tax' => $taxAmount,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'paid' => 0,
                'due' => $total,
                'payment_status' => PaymentStatus::Unpaid,
                'notes' => $validated['notes'] ?? null,
                'ordered_at' => $request->date('date') ?? now(),
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line['attributes']);
            }

            $order->statusHistories()->create([
                'status' => 'ordered',
                'occurred_at' => now(),
                'created_by' => $request->user()->id,
            ]);

            if ($paidAmount > 0) {
                $payments->record(
                    $request->user(),
                    $order,
                    min($paidAmount, $total),
                    (string) ($validated['method'] ?? 'Cash'),
                    $order->ordered_at?->toDateString(),
                );
            }

            if ($fullyPaid) {
                $order->statusHistories()->create([
                    'status' => 'delivered',
                    'note' => 'POS sale lunas',
                    'occurred_at' => now(),
                    'created_by' => $request->user()->id,
                ]);
            }

            return $order->refresh()->load(['items', 'payments']);
        });

        return ApiResponse::success(new SaleResource($order), [], 201);
    }

    public function recordPayment(RecordPaymentRequest $request, string $number, PaymentService $payments): JsonResponse
    {
        $order = Order::query()->where('number', $number)->firstOrFail();
        $amount = $request->integer('amount');

        if ($amount > (int) $order->due) {
            return ApiResponse::error('Nominal melebihi sisa tagihan.', 422, [
                'amount' => ['Nominal melebihi sisa tagihan (maksimal '.number_format((int) $order->due, 0, ',', '.').').'],
            ]);
        }

        $payments->record(
            $request->user(),
            $order,
            $amount,
            (string) ($request->input('method') ?? 'Cash'),
            $request->date('date')?->toDateString(),
            $request->input('notes'),
        );

        return ApiResponse::success(new SaleResource($order->refresh()->load(['items', 'payments'])), [], 201);
    }

    public function void(Request $request, string $number): JsonResponse
    {
        $order = Order::query()->where('number', $number)->firstOrFail();

        if ($order->status === OrderStatus::Cancelled) {
            return ApiResponse::error('Order sudah dibatalkan.', 409);
        }

        $order->forceFill([
            'status' => OrderStatus::Cancelled,
            'status_by' => $request->user()->name,
        ])->save();

        $order->statusHistories()->create([
            'status' => 'cancelled',
            'note' => $request->input('note') ?? 'Dibatalkan oleh admin.',
            'occurred_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::success(new SaleResource($order->load(['items', 'payments'])));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{total: int, attributes: array<string, mixed>}>
     */
    private function buildLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $product = isset($item['productId']) ? Product::query()->find((int) $item['productId']) : null;
            $quantity = (float) $item['quantity'];
            $price = (int) ($item['price'] ?? $product?->price_from ?? 0);

            $lines[] = [
                'total' => (int) round($price * $quantity),
                'attributes' => [
                    'product_id' => $product?->id,
                    'name' => (string) ($item['name'] ?? $product?->name),
                    'category' => $product?->category?->name,
                    'quantity' => $quantity,
                    'unit' => $item['unit'] ?? null,
                    'price' => $price,
                    'total' => (int) round($price * $quantity),
                    'spec' => (string) ($item['spec'] ?? ''),
                ],
            ];
        }

        return $lines;
    }
}
