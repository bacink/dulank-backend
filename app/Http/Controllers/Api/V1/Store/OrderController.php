<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\CheckoutRequest;
use App\Http\Resources\Store\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items', 'statusHistories'])
            ->orderBy('id')
            ->get();

        return ApiResponse::success(OrderResource::collection($orders));
    }

    public function store(CheckoutRequest $request, OrderService $orders): JsonResponse
    {
        $order = $orders->checkout($request->user(), $request->validated());

        return ApiResponse::success(new OrderResource($order), [], 201);
    }

    public function show(Request $request, string $number): JsonResponse
    {
        $order = Order::query()
            ->where('number', $number)
            ->with(['items', 'statusHistories'])
            ->first();

        if ($order === null) {
            return ApiResponse::error('Order not found.', 404);
        }

        $this->authorize('view', $order);

        return ApiResponse::success(new OrderResource($order));
    }
}
