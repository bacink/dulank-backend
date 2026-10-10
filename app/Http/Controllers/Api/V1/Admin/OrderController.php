<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Enums\ShippingMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderIndexRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Models\Order;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function index(OrderIndexRequest $request): JsonResponse
    {
        $query = Order::query()->with('items');

        $this->applyFilters($query, $request);

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $orders = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success(OrderResource::collection($orders), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function stats(): JsonResponse
    {
        return ApiResponse::success([
            'totalOrders' => Order::query()->count(),
            'totalCustomers' => (int) Order::query()->whereNotNull('user_id')->distinct()->count('user_id'),
            'totalComplete' => Order::query()->where('status', OrderStatus::Delivered)->count(),
            'totalCancel' => Order::query()->where('status', OrderStatus::Cancelled)->count(),
        ]);
    }

    public function show(string $number): JsonResponse
    {
        $order = Order::query()
            ->where('number', $number)
            ->with(['items', 'statusHistories', 'payments'])
            ->firstOrFail();

        return ApiResponse::success(new OrderResource($order));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, string $number): JsonResponse
    {
        $order = Order::query()->where('number', $number)->firstOrFail();

        $status = OrderStatus::fromAdmin($request->string('status')->toString());

        $order->forceFill([
            'status' => $status,
            'status_by' => $request->input('statusBy') ?? $request->user()->name,
        ])->save();

        $order->statusHistories()->create([
            'status' => $status->legacy(),
            'note' => $request->input('note'),
            'occurred_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::success(new OrderResource($order->load('items')));
    }

    private function applyFilters(Builder $query, OrderIndexRequest $request): void
    {
        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', OrderStatus::fromAdmin($status));
        }

        $shipping = trim((string) $request->input('shipping', ''));
        if ($shipping !== '') {
            $method = ShippingMethod::fromAdmin($shipping);
            if ($method !== null) {
                $query->where('shipping_method', $method);
            }
        }

        $channel = trim((string) $request->input('channel', ''));
        if ($channel !== '') {
            $query->where('channel', strtolower($channel));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('number', 'like', $term)
                    ->orWhere('contact->name', 'like', $term);
            });
        }

        if ($request->filled('startDate')) {
            $query->whereDate('ordered_at', '>=', $request->date('startDate'));
        }

        if ($request->filled('endDate')) {
            $query->whereDate('ordered_at', '<=', $request->date('endDate'));
        }
    }
}
