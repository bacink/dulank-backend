<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SupportTicket;
use App\Services\LedgerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(LedgerService $ledger): JsonResponse
    {
        $today = now()->toDateString();

        $totalBalance = BankAccount::query()->get()
            ->sum(fn ($account) => $ledger->balance($account));

        return ApiResponse::success([
            'ordersToday' => Order::query()->whereDate('ordered_at', $today)->count(),
            'revenueToday' => (int) Order::query()->whereDate('ordered_at', $today)->sum('total'),
            'ordersTotal' => Order::query()->count(),
            'pendingPayments' => Payment::query()->where('status', 'Pending')->count(),
            'openTickets' => SupportTicket::query()->where('status', 'Open')->count(),
            'totalBalance' => (int) $totalBalance,
            'lowStock' => Product::query()
                ->whereNotNull('quantity_alert')
                ->whereColumn('quantity', '<=', 'quantity_alert')
                ->where('status', 'Active')
                ->count(),
            'recentOrders' => Order::query()
                ->with('items')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (Order $order) => [
                    'no' => $order->number,
                    'customer' => $order->contact['name'] ?? $order->user?->name,
                    'status' => $order->status?->admin(),
                    'total' => (int) $order->total,
                    'date' => ($order->ordered_at ?? $order->created_at)?->toDateString(),
                ])->values(),
            'topProducts' => OrderItem::query()
                ->selectRaw('name, SUM(quantity) as qty')
                ->groupBy('name')
                ->orderByDesc('qty')
                ->limit(5)
                ->get()
                ->map(fn ($row) => ['name' => $row->name, 'qty' => (float) $row->qty])
                ->values(),
        ]);
    }
}
