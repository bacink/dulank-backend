<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAccountEntry;
use App\Models\Expense;
use App\Models\Income;
use App\Models\InputTaxDocument;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutputTaxDocument;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function show(Request $request, string $type): JsonResponse
    {
        return match ($type) {
            'sales' => $this->sales($request),
            'purchases' => $this->purchases($request),
            'finance' => $this->finance($request),
            'products' => $this->products(),
            'customers' => $this->customers(),
            'annual' => $this->annual($request),
            'best-seller' => $this->bestSeller($request),
            'customer-dues' => $this->customerDues(),
            'supplier-dues' => $this->supplierDues(),
            'suppliers' => $this->suppliers($request),
            'taxes' => $this->taxes($request),
            'profit-loss' => $this->profitLoss($request),
            'invoices' => $this->invoices($request),
            'expenses' => $this->expenses($request),
            'incomes' => $this->incomes($request),
            default => ApiResponse::error('Jenis laporan tidak dikenal.', 404),
        };
    }

    public function export(Request $request, string $type): StreamedResponse|BinaryFileResponse
    {
        [$headers, $rows] = $this->exportData($request, $type);

        if ($request->input('format') === 'xlsx') {
            return Excel::download(
                new ReportExport($headers, $rows),
                "report-{$type}-".now()->format('Ymd-His').'.xlsx',
            );
        }

        return response()->streamDownload(function () use ($rows, $headers): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, "report-{$type}-".now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function exportData(Request $request, string $type): array
    {
        return match ($type) {
            'sales' => [
                ['No', 'Tanggal', 'Customer', 'Status', 'Pembayaran', 'Total', 'Dibayar', 'Sisa'],
                $this->orderQuery($request)->with('items')->get()->map(fn (Order $order) => [
                    $order->number,
                    $order->ordered_at?->toDateString(),
                    $order->contact['name'] ?? '',
                    $order->status?->legacy(),
                    $order->payment_status?->value,
                    (int) $order->total,
                    (int) $order->paid,
                    (int) $order->due,
                ])->all(),
            ],
            'purchases' => [
                ['No Purchase', 'Tanggal', 'Supplier', 'Status', 'Amount', 'Paid', 'Due'],
                Purchase::query()->with(['supplier'])->orderByDesc('id')->get()->map(fn (Purchase $purchase) => [
                    $purchase->no_purchase,
                    $purchase->date?->toDateString(),
                    $purchase->supplier?->name ?? '',
                    $purchase->status,
                    (int) $purchase->amount,
                    (int) $purchase->paid,
                    (int) $purchase->due,
                ])->all(),
            ],
            default => [['Data'], []],
        };
    }

    private function sales(Request $request): JsonResponse
    {
        $query = $this->orderQuery($request);

        $byChannel = (clone $query)
            ->selectRaw('channel, COUNT(*) as total_orders, SUM(total) as total_sales')
            ->groupBy('channel')
            ->get()
            ->map(fn ($row) => [
                'channel' => $row->channel,
                'totalOrders' => (int) $row->total_orders,
                'totalSales' => (int) $row->total_sales,
            ])->values();

        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as total_orders')
            ->groupBy('status')
            ->get()
            ->map(function ($row) {
                $status = $row->status;

                return [
                    'status' => $status instanceof OrderStatus
                        ? $status->admin()
                        : (OrderStatus::tryFrom((string) $status)?->admin() ?? (string) $status),
                    'totalOrders' => (int) $row->total_orders,
                ];
            })->values();

        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_id', (clone $query)->select('id'))
            ->selectRaw('order_items.name, SUM(order_items.quantity) as qty, SUM(order_items.total) as amount')
            ->groupBy('order_items.name')
            ->orderByDesc('amount')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'qty' => (float) $row->qty,
                'amount' => (int) $row->amount,
            ])->values();

        return ApiResponse::success([
            'totalOrders' => (clone $query)->count(),
            'totalSales' => (int) (clone $query)->sum('total'),
            'totalPaid' => (int) (clone $query)->sum('paid'),
            'totalDue' => (int) (clone $query)->sum('due'),
            'byChannel' => $byChannel,
            'byStatus' => $byStatus,
            'topProducts' => $topProducts,
        ]);
    }

    private function purchases(Request $request): JsonResponse
    {
        $query = Purchase::query();

        if ($request->filled('startDate')) {
            $query->whereDate('date', '>=', $request->date('startDate'));
        }

        if ($request->filled('endDate')) {
            $query->whereDate('date', '<=', $request->date('endDate'));
        }

        $bySupplier = Purchase::query()
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->selectRaw('suppliers.name, COUNT(*) as total_purchases, SUM(purchases.amount) as total_amount')
            ->groupBy('suppliers.name')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'supplier' => $row->name,
                'totalPurchases' => (int) $row->total_purchases,
                'totalAmount' => (int) $row->total_amount,
            ])->values();

        return ApiResponse::success([
            'totalPurchases' => (clone $query)->count(),
            'totalAmount' => (int) (clone $query)->sum('amount'),
            'totalPaid' => (int) (clone $query)->sum('paid'),
            'totalDue' => (int) (clone $query)->sum('due'),
            'bySupplier' => $bySupplier,
        ]);
    }

    private function finance(Request $request): JsonResponse
    {
        $ledgerQuery = DB::table('bank_ledger_entries');

        if ($request->filled('startDate')) {
            $ledgerQuery->whereDate('occurred_at', '>=', $request->date('startDate'));
        }

        if ($request->filled('endDate')) {
            $ledgerQuery->whereDate('occurred_at', '<=', $request->date('endDate'));
        }

        return ApiResponse::success([
            'totalIncome' => (int) Income::query()->where('is_cancelled', false)->sum('amount'),
            'totalExpense' => (int) Expense::query()->sum('paid'),
            'totalReceivable' => (int) Invoice::query()->sum('amount_due'),
            'totalPayable' => (int) Purchase::query()->sum('due'),
            'totalInflow' => (int) (clone $ledgerQuery)->where('direction', 'credit')->sum('amount'),
            'totalOutflow' => (int) (clone $ledgerQuery)->where('direction', 'debit')->sum('amount'),
            'pendingPayments' => Payment::query()->where('status', 'Pending')->count(),
        ]);
    }

    private function products(): JsonResponse
    {
        $lowStock = Product::query()
            ->whereNotNull('quantity_alert')
            ->whereColumn('quantity', '<=', 'quantity_alert')
            ->where('status', 'Active')
            ->orderBy('quantity')
            ->limit(20)
            ->get()
            ->map(fn (Product $product) => [
                'id' => (string) $product->id,
                'name' => $product->name,
                'quantity' => (float) $product->quantity,
                'quantityAlert' => (float) $product->quantity_alert,
            ])->values();

        $topSelling = OrderItem::query()
            ->selectRaw('name, SUM(quantity) as qty, SUM(total) as amount')
            ->groupBy('name')
            ->orderByDesc('amount')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'qty' => (float) $row->qty,
                'amount' => (int) $row->amount,
            ])->values();

        return ApiResponse::success([
            'totalProducts' => Product::query()->count(),
            'activeProducts' => Product::query()->where('status', 'Active')->count(),
            'lowStock' => $lowStock,
            'topSelling' => $topSelling,
        ]);
    }

    private function customers(): JsonResponse
    {
        $topCustomers = CustomerAccountEntry::query()
            ->join('customers', 'customers.id', '=', 'customer_account_entries.customer_id')
            ->leftJoin('users', 'users.id', '=', 'customers.user_id')
            ->where('source_type', 'sale')
            ->selectRaw('customer_account_entries.customer_id, users.name, SUM(customer_account_entries.amount) as total_sales')
            ->groupBy('customer_account_entries.customer_id', 'users.name')
            ->orderByDesc('total_sales')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'customerId' => (string) $row->customer_id,
                'customer' => $row->name,
                'totalSales' => (int) $row->total_sales,
            ])->values();

        return ApiResponse::success([
            'totalCustomers' => Customer::query()->count(),
            'topCustomers' => $topCustomers,
        ]);
    }

    /**
     * @return Builder<Order>
     */
    private function orderQuery(Request $request): Builder
    {
        $query = Order::query();

        if ($request->filled('startDate')) {
            $query->whereDate('ordered_at', '>=', $request->date('startDate'));
        }

        if ($request->filled('endDate')) {
            $query->whereDate('ordered_at', '<=', $request->date('endDate'));
        }

        if ($request->filled('channel')) {
            $query->where('channel', strtolower((string) $request->input('channel')));
        }

        return $query;
    }

    private function annual(Request $request): JsonResponse
    {
        $year = (int) ($request->input('year') ?? now()->year);

        $orders = Order::query()
            ->whereYear('ordered_at', $year)
            ->get(['ordered_at', 'total']);

        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = ['month' => $month, 'totalOrders' => 0, 'totalSales' => 0];
        }

        foreach ($orders as $order) {
            $month = (int) $order->ordered_at?->month;

            if ($month < 1 || $month > 12) {
                continue;
            }

            $months[$month]['totalOrders']++;
            $months[$month]['totalSales'] += (int) $order->total;
        }

        $months = array_values($months);

        return ApiResponse::success([
            'year' => $year,
            'months' => $months,
            'totalOrders' => (int) collect($months)->sum('totalOrders'),
            'totalSales' => (int) collect($months)->sum('totalSales'),
        ]);
    }

    private function bestSeller(Request $request): JsonResponse
    {
        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id');

        if ($request->filled('startDate')) {
            $query->whereDate('orders.ordered_at', '>=', $request->date('startDate'));
        }
        if ($request->filled('endDate')) {
            $query->whereDate('orders.ordered_at', '<=', $request->date('endDate'));
        }

        $rows = $query
            ->selectRaw('order_items.name, SUM(order_items.quantity) as qty, SUM(order_items.total) as amount')
            ->groupBy('order_items.name')
            ->orderByDesc('qty')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'qty' => (float) $row->qty,
                'amount' => (int) $row->amount,
            ])->values();

        return ApiResponse::success(['products' => $rows]);
    }

    private function customerDues(): JsonResponse
    {
        $rows = CustomerAccountEntry::query()
            ->join('customers', 'customers.id', '=', 'customer_account_entries.customer_id')
            ->leftJoin('users', 'users.id', '=', 'customers.user_id')
            ->selectRaw("customer_account_entries.customer_id, users.name, users.email,
                SUM(CASE WHEN source_type IN ('opening_balance','sale','adjustment') THEN amount ELSE 0 END) as debit,
                SUM(CASE WHEN source_type = 'payment' THEN amount ELSE 0 END) as credit")
            ->groupBy('customer_account_entries.customer_id', 'users.name', 'users.email')
            ->havingRaw('debit - credit > 0')
            ->orderByRaw('debit - credit DESC')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'customerId' => (string) $row->customer_id,
                'customer' => $row->name,
                'email' => $row->email,
                'due' => (int) ($row->debit - $row->credit),
            ])->values();

        return ApiResponse::success([
            'totalDue' => (int) $rows->sum('due'),
            'customers' => $rows,
        ]);
    }

    private function supplierDues(): JsonResponse
    {
        $rows = Purchase::query()
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->where('purchases.due', '>', 0)
            ->selectRaw('suppliers.id, suppliers.name, SUM(purchases.due) as total_due, COUNT(*) as total_purchases')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_due')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'supplierId' => (string) $row->id,
                'supplier' => $row->name,
                'totalPurchases' => (int) $row->total_purchases,
                'due' => (int) $row->total_due,
            ])->values();

        return ApiResponse::success([
            'totalDue' => (int) $rows->sum('due'),
            'suppliers' => $rows,
        ]);
    }

    private function suppliers(Request $request): JsonResponse
    {
        $query = Purchase::query()->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id');

        if ($request->filled('startDate')) {
            $query->whereDate('purchases.date', '>=', $request->date('startDate'));
        }
        if ($request->filled('endDate')) {
            $query->whereDate('purchases.date', '<=', $request->date('endDate'));
        }

        $rows = $query
            ->selectRaw('suppliers.id, suppliers.name, COUNT(*) as total_purchases, SUM(purchases.amount) as amount, SUM(purchases.paid) as paid, SUM(purchases.due) as due')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('amount')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'supplierId' => (string) $row->id,
                'supplier' => $row->name,
                'totalPurchases' => (int) $row->total_purchases,
                'amount' => (int) $row->amount,
                'paid' => (int) $row->paid,
                'due' => (int) $row->due,
            ])->values();

        return ApiResponse::success(['suppliers' => $rows]);
    }

    private function taxes(Request $request): JsonResponse
    {
        $orders = $this->orderQuery($request);

        $output = [
            'dpp' => (int) (clone $orders)->sum('sub_total'),
            'vat' => (int) (clone $orders)->sum('tax'),
            'total' => (int) (clone $orders)->sum('total'),
            'documents' => OutputTaxDocument::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ];

        $purchases = Purchase::query();
        if ($request->filled('startDate')) {
            $purchases->whereDate('date', '>=', $request->date('startDate'));
        }
        if ($request->filled('endDate')) {
            $purchases->whereDate('date', '<=', $request->date('endDate'));
        }

        $input = [
            'dpp' => (int) (clone $purchases)->sum('amount'),
            'vat' => (int) (clone $purchases)->sum('tax'),
            'documents' => InputTaxDocument::query()
                ->selectRaw('credited, COUNT(*) as total')
                ->groupBy('credited')
                ->pluck('total', 'credited'),
        ];

        return ApiResponse::success(['output' => $output, 'input' => $input]);
    }

    private function profitLoss(Request $request): JsonResponse
    {
        $orders = $this->orderQuery($request);

        $revenue = (int) (clone $orders)->sum('total');
        $purchases = Purchase::query();
        if ($request->filled('startDate')) {
            $purchases->whereDate('date', '>=', $request->date('startDate'));
        }
        if ($request->filled('endDate')) {
            $purchases->whereDate('date', '<=', $request->date('endDate'));
        }

        $cogs = (int) (clone $purchases)->sum('amount');
        $expense = (int) Expense::query()->sum('paid');
        $income = (int) Income::query()->where('is_cancelled', false)->sum('amount');

        return ApiResponse::success([
            'revenue' => $revenue,
            'cogs' => $cogs,
            'grossProfit' => $revenue - $cogs,
            'expense' => $expense,
            'otherIncome' => $income,
            'netProfit' => $revenue - $cogs - $expense + $income,
        ]);
    }

    private function invoices(Request $request): JsonResponse
    {
        $query = Invoice::query();
        if ($request->filled('startDate')) {
            $query->whereDate('invoice_date', '>=', $request->date('startDate'));
        }
        if ($request->filled('endDate')) {
            $query->whereDate('invoice_date', '<=', $request->date('endDate'));
        }

        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as total, SUM(amount) as amount, SUM(paid) as paid, SUM(amount_due) as due')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'total' => (int) $row->total,
                'amount' => (int) $row->amount,
                'paid' => (int) $row->paid,
                'due' => (int) $row->due,
            ])->values();

        return ApiResponse::success([
            'totalInvoices' => (clone $query)->count(),
            'totalAmount' => (int) (clone $query)->sum('amount'),
            'totalPaid' => (int) (clone $query)->sum('paid'),
            'totalDue' => (int) (clone $query)->sum('amount_due'),
            'byStatus' => $byStatus,
        ]);
    }

    private function expenses(Request $request): JsonResponse
    {
        $query = Expense::query()->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id');

        if ($request->filled('startDate')) {
            $query->whereDate('expenses.date', '>=', $request->date('startDate'));
        }
        if ($request->filled('endDate')) {
            $query->whereDate('expenses.date', '<=', $request->date('endDate'));
        }

        $byCategory = $query
            ->selectRaw('COALESCE(expense_categories.name, "Tanpa Kategori") as category, COUNT(*) as total, SUM(expenses.amount) as amount, SUM(expenses.paid) as paid, SUM(expenses.due) as due')
            ->groupBy('category')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'total' => (int) $row->total,
                'amount' => (int) $row->amount,
                'paid' => (int) $row->paid,
                'due' => (int) $row->due,
            ])->values();

        return ApiResponse::success([
            'totalAmount' => (int) $byCategory->sum('amount'),
            'totalPaid' => (int) $byCategory->sum('paid'),
            'byCategory' => $byCategory,
        ]);
    }

    private function incomes(Request $request): JsonResponse
    {
        $query = Income::query()
            ->leftJoin('income_categories', 'income_categories.id', '=', 'incomes.income_category_id')
            ->where('incomes.is_cancelled', false);

        if ($request->filled('startDate')) {
            $query->whereDate('incomes.date', '>=', $request->date('startDate'));
        }
        if ($request->filled('endDate')) {
            $query->whereDate('incomes.date', '<=', $request->date('endDate'));
        }

        $byCategory = $query
            ->selectRaw('COALESCE(income_categories.name, "Tanpa Kategori") as category, COUNT(*) as total, SUM(incomes.amount) as amount')
            ->groupBy('category')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'total' => (int) $row->total,
                'amount' => (int) $row->amount,
            ])->values();

        return ApiResponse::success([
            'totalAmount' => (int) $byCategory->sum('amount'),
            'byCategory' => $byCategory,
        ]);
    }
}
