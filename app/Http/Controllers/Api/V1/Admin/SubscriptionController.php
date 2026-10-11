<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Subscription;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Subskripsi pelanggan webstore.
 */
class SubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::query();

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('subscriber', 'like', '%'.$search.'%');
        }

        $plan = trim((string) $request->input('plan', ''));
        if ($plan !== '') {
            $query->where('plan', $plan);
        }

        $paymentMethod = trim((string) $request->input('paymentMethod', ''));
        if ($paymentMethod !== '') {
            $query->where('payment_method', $paymentMethod);
        }

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($request->filled('startDate')) {
            $query->whereDate('created_date', '>=', $request->date('startDate'));
        }

        if ($request->filled('endDate')) {
            $query->whereDate('created_date', '<=', $request->date('endDate'));
        }

        $records = $query->orderByDesc('id')->limit(200)->get()->map(fn (Subscription $subscription) => $this->payload($subscription))->values();
        $today = now()->toDateString();

        return response()->json([
            'success' => true,
            'data' => $records->all(),
            'summary' => [
                'totalTransactions' => $records->count(),
                'totalSubscribers' => Subscription::query()->distinct()->count('subscriber'),
                'activeSubscribers' => Subscription::query()->where('status', 'Paid')->whereDate('expiring_on', '>=', $today)->count(),
                'expiredSubscribers' => Subscription::query()->whereDate('expiring_on', '<', $today)->count(),
            ],
        ]);
    }

    public function update(Request $request, Subscription $subscription): JsonResponse
    {
        $validated = $request->validate([
            'subscriber' => ['sometimes', 'string', 'max:255'],
            'plan' => ['sometimes', 'nullable', 'string', 'max:255'],
            'planTier' => ['sometimes', 'nullable', 'in:Basic,Advanced,Enterprise'],
            'billingCycle' => ['sometimes', 'nullable', 'in:30 Days,365 Days'],
            'paymentMethod' => ['sometimes', 'nullable', 'string', 'max:40'],
            'amount' => ['sometimes', 'integer', 'min:0'],
            'createdDate' => ['sometimes', 'nullable', 'date'],
            'expiringOn' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'in:Paid,Unpaid'],
        ]);

        $subscription->forceFill([
            'subscriber' => $validated['subscriber'] ?? $subscription->subscriber,
            'plan' => array_key_exists('plan', $validated) ? $validated['plan'] : $subscription->plan,
            'plan_tier' => $validated['planTier'] ?? $subscription->plan_tier,
            'billing_cycle' => $validated['billingCycle'] ?? $subscription->billing_cycle,
            'payment_method' => array_key_exists('paymentMethod', $validated) ? $validated['paymentMethod'] : $subscription->payment_method,
            'amount' => $validated['amount'] ?? $subscription->amount,
            'created_date' => $request->date('createdDate') ?? $subscription->created_date,
            'expiring_on' => $request->date('expiringOn') ?? $subscription->expiring_on,
            'status' => $validated['status'] ?? $subscription->status,
        ])->save();

        return ApiResponse::success($this->payload($subscription->refresh()));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Subscription $subscription): array
    {
        return [
            'id' => (string) $subscription->id,
            'subscriber' => $subscription->subscriber,
            'planTier' => $subscription->plan_tier,
            'plan' => $subscription->plan,
            'billingCycle' => $subscription->billing_cycle,
            'paymentMethod' => $subscription->payment_method,
            'amount' => (int) $subscription->amount,
            'createdDate' => $subscription->created_date?->toDateString(),
            'expiringOn' => $subscription->expiring_on?->toDateString(),
            'status' => $subscription->status,
            'createdAt' => $subscription->created_at?->toIso8601String(),
            'updatedAt' => $subscription->updated_at?->toIso8601String(),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customerId' => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'subscriber' => ['sometimes', 'nullable', 'string', 'max:255'],
            'plan' => ['sometimes', 'nullable', 'string', 'max:255'],
            'planTier' => ['sometimes', 'nullable', 'in:Basic,Advanced,Enterprise'],
            'billingCycle' => ['sometimes', 'nullable', 'in:30 Days,365 Days'],
            'paymentMethod' => ['sometimes', 'nullable', 'string', 'max:40'],
            'amount' => ['required', 'integer', 'min:0'],
            'createdDate' => ['sometimes', 'nullable', 'date'],
            'expiringOn' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'in:Paid,Unpaid'],
        ]);

        $customer = isset($validated['customerId'])
            ? Customer::query()->with('user')->find((int) $validated['customerId'])
            : null;

        $subscription = Subscription::query()->create([
            'customer_id' => $customer?->id,
            'subscriber' => $validated['subscriber'] ?? $customer?->user?->name ?? 'Pelanggan',
            'plan' => $validated['plan'] ?? null,
            'plan_tier' => $validated['planTier'] ?? 'Basic',
            'billing_cycle' => $validated['billingCycle'] ?? '30 Days',
            'payment_method' => $validated['paymentMethod'] ?? null,
            'amount' => (int) $validated['amount'],
            'created_date' => $request->date('createdDate')?->toDateString() ?? now()->toDateString(),
            'expiring_on' => $request->date('expiringOn')?->toDateString(),
            'status' => $validated['status'] ?? 'Unpaid',
        ]);

        return ApiResponse::success($this->payload($subscription->load('customer.user')), [], 201);
    }

    /**
     * Space pelanggan dengan status subskripsinya (baris per pelanggan).
     */
    public function customerIndex(Request $request): JsonResponse
    {
        $query = Customer::query()->with('user');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->whereHas('user', fn (Builder $query) => $query
                ->where('name', 'like', $term)
                ->orWhere('email', 'like', $term));
        }

        $customers = $query->orderByDesc('id')->limit(200)->get();
        $ids = $customers->pluck('id');

        $subscriptions = Subscription::query()
            ->whereIn('customer_id', $ids)
            ->orderByDesc('id')
            ->get()
            ->groupBy('customer_id')
            ->map(fn ($rows) => $rows->first());
        $today = now()->toDateString();

        $statusFilter = trim((string) $request->input('status', ''));
        $planFilter = trim((string) $request->input('plan', ''));

        $rows = $customers->filter(function (Customer $customer) use ($statusFilter, $planFilter, $subscriptions): bool {
            $subscription = $subscriptions->get($customer->id);

            if ($statusFilter !== '') {
                if ($statusFilter === 'Aktif' && (! $subscription || $subscription->status !== 'Paid')) {
                    return false;
                }
                if ($statusFilter === 'Kadaluarsa' && $subscription?->status === 'Paid' && ($subscription->expiring_on === null || $subscription->expiring_on->toDateString() >= now()->toDateString())) {
                    return false;
                }
                if ($statusFilter === 'Belum' && $subscription !== null) {
                    return false;
                }
            }

            if ($planFilter !== '' && ($subscription?->plan === null || $subscription->plan !== $planFilter)) {
                return false;
            }

            return true;
        })->map(function (Customer $customer) use ($subscriptions, $today): array {
            $subscription = $subscriptions->get($customer->id);
            $active = $subscription !== null
                && $subscription->status === 'Paid'
                && ($subscription->expiring_on === null || $subscription->expiring_on->toDateString() >= $today);

            return [
                'id' => $subscription !== null ? (string) $subscription->id : null,
                'customerId' => (string) $customer->id,
                'customer' => $customer->user?->name ?? 'Tanpa Nama',
                'email' => $customer->user?->email ?? '',
                'plan' => $subscription?->plan,
                'planTier' => $subscription?->plan_tier ?? null,
                'billingCycle' => $subscription?->billing_cycle ?? null,
                'paymentMethod' => $subscription?->payment_method ?? null,
                'amount' => (int) ($subscription?->amount ?? 0),
                'createdDate' => $subscription?->created_date?->toDateString(),
                'expiringOn' => $subscription?->expiring_on?->toDateString(),
                'status' => $subscription?->status ?? null,
                'active' => $active,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $rows->all(),
            'summary' => [
                'totalSubscribers' => Subscription::query()->count(),
                'activeSubscribers' => Subscription::query()->where('status', 'Paid')->whereDate('expiring_on', '>=', $today)->count(),
                'expiredSubscribers' => Subscription::query()->whereDate('expiring_on', '<', $today)->count(),
            ],
        ]);
    }
}
