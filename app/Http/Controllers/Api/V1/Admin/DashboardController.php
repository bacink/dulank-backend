<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Quotation;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\SupportTicket;
use App\Services\LedgerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /**
     * Dashboard admin tambahan (Rama): kartu, overview, produk, grafik & transaksi.
     *
     * @return array<string, mixed>
     */
    public function adminStats(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $monthTrend = $this->monthlyTrend();
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $labels = array_map(fn (array $month): string => $monthNames[($month['month'] - 1) % 12] ?? '?', $monthTrend);

        $topProducts = OrderItem::query()
            ->selectRaw('name, SUM(quantity) as sales')
            ->groupBy('name')
            ->orderByDesc('sales')
            ->limit(8)
            ->get();
        $productNames = $topProducts->pluck('name');
        $productMap = Product::query()->with(['images', 'category'])->whereIn('name', $productNames)->get()->keyBy('name');

        $lowStockRows = Product::query()
            ->whereNotNull('quantity_alert')->whereColumn('quantity', '<=', 'quantity_alert')
            ->where('status', 'Active')->limit(8)->get();

        $recentSales = Order::query()->orderByDesc('id')->limit(8)->get()->map(fn (Order $order) => [
            'id' => (string) $order->id,
            'name' => $order->contact['name'] ?? $order->user?->name,
            'image' => '',
            'category' => $order->channel?->label() ?? '-',
            'amount' => (int) $order->total,
            'date' => ($order->ordered_at ?? $order->created_at)?->toDateString(),
            'status' => $order->status?->admin() ?? 'Pending',
        ])->values();

        $totalSales = (int) Order::query()->whereNot('status', OrderStatus::Cancelled)->sum('total');
        $totalIncome = (int) Income::query()->where('is_cancelled', false)->sum('amount');
        $totalExpense = (int) Expense::query()->sum('paid');

        $transactions = function (string $label, string $subject, array $rows): array {
            return [
                'id' => $label, 'label' => ucfirst($label), 'subjectLabel' => $subject,
                'dateLabel' => 'Tanggal', 'amountLabel' => 'Jumlah', 'rows' => $rows,
            ];
        };

        return ApiResponse::success([
            'welcomeName' => $request->user()->name,
            'ordersToday' => Order::query()->whereDate('ordered_at', $today)->count(),
            'lowStockNotice' => [
                'productName' => $lowStockRows->first()?->name ?? '-',
                'threshold' => (int) ($lowStockRows->first()?->quantity_alert ?? 0),
                'link' => '/product-list',
            ],
            'primaryMetrics' => [
                ['id' => 'sales', 'title' => 'Total Sales', 'amount' => $totalSales, 'change' => 0, 'icon' => 'trending-up', 'tone' => 'orange', 'link' => '/sales'],
                ['id' => 'sales-return', 'title' => 'Total Sales Return', 'amount' => (int) SalesReturn::query()->sum('total'), 'change' => 0, 'icon' => 'rotate-ccw', 'tone' => 'navy', 'link' => '/sales-return'],
                ['id' => 'purchase', 'title' => 'Total Purchase', 'amount' => (int) Purchase::query()->sum('amount'), 'change' => 0, 'icon' => 'shopping-bag', 'tone' => 'green', 'link' => '/purchase'],
                ['id' => 'income', 'title' => 'Total Income', 'amount' => $totalIncome, 'change' => 0, 'icon' => 'file-minus', 'tone' => 'blue', 'link' => '/income'],
            ],
            'secondaryMetrics' => [
                ['id' => 'profit', 'title' => 'Profit', 'amount' => max($totalIncome - $totalExpense, 0), 'change' => 0, 'icon' => 'layers', 'tone' => 'plain', 'link' => '/profit-and-loss'],
                ['id' => 'invoice-due', 'title' => 'Invoice Due', 'amount' => (int) Invoice::query()->sum('amount_due'), 'change' => 0, 'icon' => 'pie-chart', 'tone' => 'plain', 'link' => '/invoice-report'],
                ['id' => 'expenses', 'title' => 'Total Expenses', 'amount' => $totalExpense, 'change' => 0, 'icon' => 'activity', 'tone' => 'plain', 'link' => '/expenses'],
                ['id' => 'pending', 'title' => 'Pending Payments', 'amount' => Payment::query()->where('status', 'Pending')->count(), 'change' => 0, 'icon' => 'credit-card', 'tone' => 'plain', 'link' => '/payments'],
            ],
            'chartSeries' => [
                '1D' => ['labels' => $labels, 'sales' => array_column($monthTrend, 'sales'), 'purchase' => array_column($monthTrend, 'purchases')],
                '1W' => ['labels' => $labels, 'sales' => array_column($monthTrend, 'sales'), 'purchase' => array_column($monthTrend, 'purchases')],
                '1M' => ['labels' => $labels, 'sales' => array_column($monthTrend, 'sales'), 'purchase' => array_column($monthTrend, 'purchases')],
                '1Y' => ['labels' => $labels, 'sales' => array_column($monthTrend, 'sales'), 'purchase' => array_column($monthTrend, 'purchases')],
            ],
            'overview' => [
                'suppliers' => Supplier::query()->count(),
                'customers' => Customer::query()->count(),
                'orders' => Order::query()->count(),
                'firstTime' => 0, 'firstTimeChange' => 0,
                'returning' => 0, 'returningChange' => 0,
            ],
            'topSelling' => $topProducts->map(function ($row) use ($productMap): array {
                $product = $productMap->get($row->name);

                return [
                    'id' => (string) ($product->id ?? $row->name),
                    'name' => $row->name,
                    'image' => $product?->images->first()?->path ?? '',
                    'price' => (int) ($product->price ?? 0),
                    'sales' => (int) $row->sales,
                    'change' => 0,
                    'sku' => $product->code ?? '',
                    'stock' => $product?->quantity !== null ? (int) $product->quantity : null,
                    'stockTone' => null,
                ];
            })->values(),
            'lowStock' => $lowStockRows->map(fn (Product $product) => [
                'id' => (string) $product->id,
                'name' => $product->name,
                'image' => $product->images->first()?->path ?? '',
                'price' => (int) $product->price,
                'sales' => 0,
                'change' => 0,
                'sku' => $product->code,
                'stock' => (int) $product->quantity,
                'stockTone' => (int) $product->quantity <= 0 ? 'danger' : 'warning',
            ])->values(),
            'recentSales' => $recentSales,
            'salesStatics' => [
                'year' => (int) now()->year,
                'revenue' => $totalSales,
                'expense' => $totalExpense,
                'revenueChange' => 0,
                'expenseChange' => 0,
                'points' => array_column($monthTrend, 'sales'),
            ],
            'transactionGroups' => [
                $transactions('sale', 'Sales', Order::query()->orderByDesc('id')->limit(4)->get()->map(fn ($o) => ['id' => (string) $o->id, 'date' => ($o->ordered_at ?? $o->created_at)?->toDateString(), 'name' => $o->contact['name'] ?? '-', 'code' => $o->number, 'status' => $o->status?->admin() ?? '-', 'amount' => (int) $o->total, 'image' => ''])->values()->all()),
                $transactions('purchase', 'Purchase', Purchase::query()->orderByDesc('id')->limit(4)->get()->map(fn ($p) => ['id' => (string) $p->id, 'date' => $p->date?->toDateString(), 'name' => $p->supplier?->name ?? '-', 'code' => $p->no_purchase, 'status' => $p->status, 'amount' => (int) $p->amount, 'image' => ''])->values()->all()),
                $transactions('quotation', 'Quotation', Quotation::query()->orderByDesc('id')->limit(4)->get()->map(fn ($q) => ['id' => (string) $q->id, 'date' => $q->date?->toDateString(), 'name' => $q->customer_name ?? '-', 'code' => $q->no_quotation, 'status' => $q->status ?? '-', 'amount' => (int) $q->total, 'image' => ''])->values()->all()),
                $transactions('expense', 'Expense', Expense::query()->orderByDesc('id')->limit(4)->get()->map(fn ($e) => ['id' => (string) $e->id, 'date' => $e->date?->toDateString(), 'name' => $e->name ?? '-', 'code' => $e->no_expense ?? '', 'status' => $e->status ?? '-', 'amount' => (int) ($e->paid ?? $e->amount), 'image' => ''])->values()->all()),
                $transactions('invoice', 'Invoice', Invoice::query()->orderByDesc('id')->limit(4)->get()->map(fn ($i) => ['id' => (string) $i->id, 'date' => $i->invoice_date?->toDateString(), 'name' => $i->order?->contact['name'] ?? $i->user?->name ?? '-', 'code' => $i->invoice_no, 'status' => $i->status?->value ?? '-', 'amount' => (int) $i->amount, 'image' => ''])->values()->all()),
            ],
            'topCustomers' => CustomerAccountEntry::query()
                ->join('customers', 'customers.id', '=', 'customer_account_entries.customer_id')
                ->leftJoin('users', 'users.id', '=', 'customers.user_id')
                ->where('source_type', 'sale')
                ->selectRaw('customer_account_entries.customer_id, users.name, SUM(customer_account_entries.amount) as total')
                ->groupBy('customer_account_entries.customer_id', 'users.name')
                ->orderByDesc('total')->limit(5)->get()
                ->map(fn ($row) => ['id' => (string) $row->customer_id, 'name' => $row->name ?? '-', 'country' => 'Indonesia', 'orders' => 1, 'amount' => (int) $row->total, 'image' => ''])->values(),
            'categories' => Category::query()->withCount('products')->orderByDesc('products_count')->limit(6)->get()
                ->map(fn (Category $category, int $index) => [
                    'id' => (string) $category->id,
                    'name' => $category->name,
                    'sales' => (int) $category->products_count,
                    'color' => ['#ff9f43', '#052938', '#18b39b', '#2f7ff5', '#7367f0', '#ea5455'][$index] ?? '#7367f0',
                ])->values(),
            'categoryTotals' => ['categories' => Category::query()->count(), 'products' => Product::query()->count()],
            'orderStatistics' => ['labels' => $labels, 'values' => [array_column($monthTrend, 'sales'), array_column($monthTrend, 'purchases')]],
        ]);
    }

    /**
     * Dashboard penjualan (Rama): best seller, transaksi terakhir, grafik tahunan.
     */
    public function salesStats(): JsonResponse
    {
        $bestSellers = OrderItem::query()
            ->selectRaw('name, SUM(quantity) as sales')
            ->groupBy('name')->orderByDesc('sales')->limit(5)->get();
        $productMap = Product::query()->with(['images', 'category'])->whereIn('name', $bestSellers->pluck('name'))->get()->keyBy('name');

        $trend = $this->monthlyTrend();

        return ApiResponse::success([
            'bestSellers' => $bestSellers->map(fn ($row) => [
                'id' => (int) ($productMap->get($row->name)?->id ?? 0),
                'name' => $row->name,
                'price' => (int) ($productMap->get($row->name)?->price ?? 0),
                'sales' => (int) $row->sales,
                'image' => $productMap->get($row->name)?->images->first()?->path ?? '',
                'category' => $productMap->get($row->name)?->category?->name ?? '-',
                'stock' => (int) ($productMap->get($row->name)?->quantity ?? 0),
            ])->values(),
            'recentTransactions' => Order::query()->orderByDesc('id')->limit(6)->get()->map(fn (Order $order) => [
                'id' => (int) $order->id,
                'name' => $order->contact['name'] ?? $order->user?->name ?? '-',
                'time' => ($order->ordered_at ?? $order->created_at)?->format('H:i'),
                'paymentMethod' => $order->payment_method ?? 'Cash',
                'reference' => $order->number,
                'status' => match ($order->status?->admin()) {
                    'Complete' => 'Success',
                    'Cancel' => 'Canceled',
                    default => 'Pending',
                },
                'amount' => (int) $order->total,
                'image' => '',
                'date' => ($order->ordered_at ?? $order->created_at)?->toDateString(),
            ])->values(),
            'countryMarkers' => [],
            'yearlyData' => ['sales' => array_column($trend, 'sales'), 'purchase' => array_column($trend, 'purchases')],
        ]);
    }

    /**
     * Dashboard analitik (Rama): KPI + konversi; dimensi web-analytics
     * (browser/region/halaman) belum direkam backend → dikosongkan.
     */
    public function analyticsStats(): JsonResponse
    {
        $totalSales = (int) Order::query()->whereNot('status', OrderStatus::Cancelled)->sum('total');
        $paid = (int) Order::query()->whereNot('status', OrderStatus::Cancelled)->sum('paid');

        return ApiResponse::success([
            'kpis' => [
                ['title' => 'Total Sales', 'value' => number_format($totalSales, 0, ',', '.'), 'change' => '+0%', 'direction' => 'up', 'icon' => 'trending-up'],
                ['title' => 'Revenue Today', 'value' => number_format((int) Order::query()->whereDate('ordered_at', now()->toDateString())->sum('total'), 0, ',', '.'), 'change' => '+0%', 'direction' => 'up', 'icon' => 'dollar-sign'],
                ['title' => 'Orders Today', 'value' => (string) Order::query()->whereDate('ordered_at', now()->toDateString())->count(), 'change' => '+0%', 'direction' => 'up', 'icon' => 'shopping-cart'],
                ['title' => 'Pending Payments', 'value' => (string) Payment::query()->where('status', 'Pending')->count(), 'change' => '+0%', 'direction' => 'up', 'icon' => 'clock'],
            ],
            'conversion' => [
                'rate' => $totalSales > 0 ? (int) round(($paid / $totalSales) * 100) : 0,
                'thisWeek' => '-',
                'lastWeek' => '-',
            ],
            'performance' => ['today' => ['pageViews' => [], 'clicks' => []]],
            'browsers' => [],
            'regions' => [],
            'topPages' => [],
        ]);
    }
}
