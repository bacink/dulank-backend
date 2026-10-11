<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\SalesReturn;
use App\Models\Supplier;
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
            // Ringkasan operasional hari ini.
            'ordersToday' => Order::query()->whereDate('ordered_at', $today)->count(),
            'revenueToday' => (int) Order::query()->whereDate('ordered_at', $today)->sum('total'),
            'ordersTotal' => Order::query()->count(),
            'pendingPayments' => Payment::query()->where('status', 'Pending')->count(),
            'openTickets' => SupportTicket::query()->where('status', 'Open')->count(),
            'totalBalance' => (int) $totalBalance,

            // KPI kartu dashboard (uang dalam rupiah integer).
            'totalSales' => (int) Order::query()->whereNot('status', OrderStatus::Cancelled)->sum('total'),
            'totalSalesReturn' => (int) SalesReturn::query()->sum('total'),
            'totalPurchase' => (int) Purchase::query()->sum('amount'),
            'totalPurchaseReturn' => (int) PurchaseReturn::query()->sum('amount'),
            'totalIncome' => (int) Income::query()->where('is_cancelled', false)->sum('amount'),
            'totalExpense' => (int) Expense::query()->sum('paid'),
            'invoiceDue' => (int) Invoice::query()->sum('amount_due'),
            'totalCustomers' => Customer::query()->count(),
            'totalSuppliers' => Supplier::query()->count(),

            // Stok menipis & data pendukung.
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
            'monthlyTrend' => $this->monthlyTrend(),
        ]);
    }

    /**
     * Rangkaian penjualan & pembelian per bulan (Jan–Des) tahun berjalan
     * untuk grafik overview dashboard.
     *
     * @return array<int, array{month: int, sales: int, purchases: int}>
     */
    private function monthlyTrend(): array
    {
        $year = now()->year;
        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = ['month' => $month, 'sales' => 0, 'purchases' => 0];
        }

        Order::query()
            ->whereYear('ordered_at', $year)
            ->whereNot('status', OrderStatus::Cancelled)
            ->pluck('total', 'ordered_at')
            ->each(function (int $total, ?string $date) use (&$months): void {
                $month = $date !== null ? (int) substr($date, 5, 2) : 0;

                if ($month >= 1 && $month <= 12) {
                    $months[$month]['sales'] += $total;
                }
            });

        Purchase::query()
            ->whereYear('date', $year)
            ->pluck('amount', 'date')
            ->each(function (int $amount, ?string $date) use (&$months): void {
                $month = $date !== null ? (int) substr($date, 5, 2) : 0;

                if ($month >= 1 && $month <= 12) {
                    $months[$month]['purchases'] += $amount;
                }
            });

        return array_values($months);
    }
}
