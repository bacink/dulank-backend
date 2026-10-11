<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\Banner;
use App\Models\Coupon;
use App\Models\Discount;
use App\Models\DiscountPlan;
use App\Models\Voucher;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Promo: banners, coupons, vouchers, discount plans & discounts.
 */
class PromoController extends Controller
{
    public function banners(AdminIndexRequest $request): JsonResponse
    {
        $query = Banner::query();

        $type = trim((string) $request->input('type', ''));
        if ($type !== '') {
            $query->where('type', $type);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('title', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderBy('sort_order')->get()->map(fn (Banner $banner) => $this->bannerPayload($banner)));
    }

    /**
     * @return array<string, mixed>
     */
    private function bannerPayload(Banner $banner): array
    {
        return [
            'id' => (string) $banner->id,
            'title' => $banner->title,
            'imageUrl' => $banner->image_url,
            'src' => $banner->image_url,
            'type' => $banner->type?->value,
            'position' => $banner->position,
            'redirectUrl' => $banner->redirect_url,
            'startDate' => $banner->start_date?->toDateString(),
            'endDate' => $banner->end_date?->toDateString(),
            'status' => $banner->status,
            'order' => $banner->sort_order,
            'description' => $banner->description,
        ];
    }

    public function storeBanner(Request $request): JsonResponse
    {
        $validated = $this->validateBanner($request);

        $banner = Banner::query()->create($this->bannerAttributes($validated, $request));

        return ApiResponse::success($this->bannerPayload($banner), [], 201);
    }

    public function updateBanner(Request $request, Banner $banner): JsonResponse
    {
        $validated = $this->validateBanner($request);

        $banner->forceFill($this->bannerAttributes($validated, $request, $banner))->save();

        return ApiResponse::success($this->bannerPayload($banner->refresh()));
    }

    public function destroyBanner(Banner $banner): JsonResponse
    {
        $banner->delete();

        return ApiResponse::success(['id' => (string) $banner->id, 'removed' => true]);
    }

    public function coupons(AdminIndexRequest $request): JsonResponse
    {
        $query = Coupon::query();
        $this->applySearch($query, $request, ['name', 'code']);

        return ApiResponse::success($query->orderByDesc('id')->get()->map(fn (Coupon $coupon) => $this->couponPayload($coupon)));
    }

    public function storeCoupon(Request $request): JsonResponse
    {
        $validated = $this->validateCoupon($request);

        $coupon = Coupon::query()->create($this->couponAttributes($validated, $request));

        return ApiResponse::success($this->couponPayload($coupon), [], 201);
    }

    public function updateCoupon(Request $request, Coupon $coupon): JsonResponse
    {
        $validated = $this->validateCoupon($request);

        $coupon->forceFill($this->couponAttributes($validated, $request))->save();

        return ApiResponse::success($this->couponPayload($coupon->refresh()));
    }

    public function destroyCoupon(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return ApiResponse::success(['id' => (string) $coupon->id, 'removed' => true]);
    }

    public function vouchers(AdminIndexRequest $request): JsonResponse
    {
        $query = Voucher::query();
        $this->applySearch($query, $request, ['name', 'code']);

        return ApiResponse::success($query->orderByDesc('id')->get()->map(fn (Voucher $voucher) => $this->voucherPayload($voucher)));
    }

    public function storeVoucher(Request $request): JsonResponse
    {
        $validated = $this->validateVoucher($request);

        $voucher = Voucher::query()->create($this->voucherAttributes($validated, $request));

        return ApiResponse::success($this->voucherPayload($voucher), [], 201);
    }

    public function updateVoucher(Request $request, Voucher $voucher): JsonResponse
    {
        $validated = $this->validateVoucher($request);

        $voucher->forceFill($this->voucherAttributes($validated, $request))->save();

        return ApiResponse::success($this->voucherPayload($voucher->refresh()));
    }

    public function destroyVoucher(Voucher $voucher): JsonResponse
    {
        $voucher->delete();

        return ApiResponse::success(['id' => (string) $voucher->id, 'removed' => true]);
    }

    public function discountPlans(AdminIndexRequest $request): JsonResponse
    {
        $query = DiscountPlan::query()->withCount('discounts');
        $this->applySearch($query, $request, ['name']);

        return ApiResponse::success($query->orderByDesc('id')->get()->map(fn (DiscountPlan $plan) => $this->planPayload($plan)));
    }

    public function storeDiscountPlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'customers' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $plan = DiscountPlan::query()->create([
            'name' => $validated['name'],
            'customers' => $validated['customers'] ?? null,
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success($this->planPayload($plan), [], 201);
    }

    public function destroyDiscountPlan(DiscountPlan $discountPlan): JsonResponse
    {
        $discountPlan->delete();

        return ApiResponse::success(['id' => (string) $discountPlan->id, 'removed' => true]);
    }

    public function updateDiscountPlan(Request $request, DiscountPlan $discountPlan): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'customers' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $discountPlan->forceFill([
            'name' => $validated['name'],
            'customers' => $validated['customers'] ?? $discountPlan->customers,
            'status' => $validated['status'] ?? $discountPlan->status,
        ])->save();

        return ApiResponse::success($this->planPayload($discountPlan->loadCount('discounts')));
    }

    public function discounts(AdminIndexRequest $request): JsonResponse
    {
        $query = Discount::query()->with('plan');
        $this->applySearch($query, $request, ['name']);

        return ApiResponse::success($query->orderByDesc('id')->get()->map(fn (Discount $discount) => $this->discountPayload($discount)));
    }

    public function storeDiscount(Request $request): JsonResponse
    {
        $validated = $this->validateDiscount($request);

        $discount = Discount::query()->create($this->discountAttributes($validated, $request));

        return ApiResponse::success($this->discountPayload($discount->load('plan')), [], 201);
    }

    public function updateDiscount(Request $request, Discount $discount): JsonResponse
    {
        $validated = $this->validateDiscount($request);

        $discount->forceFill($this->discountAttributes($validated, $request))->save();

        return ApiResponse::success($this->discountPayload($discount->refresh()->load('plan')));
    }

    public function destroyDiscount(Discount $discount): JsonResponse
    {
        $discount->delete();

        return ApiResponse::success(['id' => (string) $discount->id, 'removed' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function discountPayload(Discount $discount): array
    {
        return [
            'id' => (string) $discount->id,
            'planId' => $discount->discount_plan_id !== null ? (string) $discount->discount_plan_id : null,
            'plan' => $discount->plan?->name,
            'name' => $discount->name,
            'value' => (int) $discount->value,
            'type' => $discount->type?->value,
            'validFrom' => $discount->valid_from?->toDateString(),
            'validTill' => $discount->valid_till?->toDateString(),
            'used' => (int) $discount->used,
            'status' => $discount->status,
        ];
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<int, string>  $columns
     */
    private function applySearch(Builder $query, AdminIndexRequest $request, array $columns): void
    {
        $search = trim((string) $request->input('search', ''));
        if ($search === '') {
            return;
        }

        $term = '%'.$search.'%';
        $query->where(function (Builder $query) use ($columns, $term): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', $term);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function validateBanner(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'imageUrl' => ['sometimes', 'nullable', 'string', 'max:500'],
            'src' => ['sometimes', 'nullable', 'string', 'max:500'],
            'type' => ['sometimes', 'nullable', 'in:main,product'],
            'position' => ['sometimes', 'nullable', 'string', 'max:60'],
            'redirectUrl' => ['sometimes', 'nullable', 'string', 'max:500'],
            'startDate' => ['sometimes', 'nullable', 'date'],
            'endDate' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
            'order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function bannerAttributes(array $validated, Request $request, ?Banner $banner = null): array
    {
        return [
            'title' => $validated['title'],
            'image_url' => $validated['imageUrl'] ?? $validated['src'] ?? $banner?->image_url,
            'type' => $validated['type'] ?? $banner?->type?->value ?? 'main',
            'position' => is_numeric($validated['position'] ?? null)
                ? (int) $validated['position']
                : ($banner?->position ?? 0),
            'redirect_url' => $validated['redirectUrl'] ?? null,
            'start_date' => $request->date('startDate')?->toDateString() ?? $banner?->start_date?->toDateString(),
            'end_date' => $request->date('endDate')?->toDateString() ?? $banner?->end_date?->toDateString(),
            'status' => $validated['status'] ?? $banner?->status ?? 'Active',
            'sort_order' => (int) ($validated['order'] ?? $banner?->sort_order ?? 0),
            'description' => $validated['description'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCoupon(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:60'],
            'type' => ['sometimes', 'nullable', 'in:Fixed,Percentage'],
            'discount' => ['required', 'integer', 'min:0'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'validFrom' => ['sometimes', 'nullable', 'date'],
            'validUntil' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function couponAttributes(array $validated, Request $request): array
    {
        return [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'type' => $validated['type'] ?? 'Fixed',
            'discount' => (int) $validated['discount'],
            'limit' => $validated['limit'] ?? null,
            'valid_from' => $request->date('validFrom')?->toDateString(),
            'valid_until' => $request->date('validUntil')?->toDateString(),
            'status' => $validated['status'] ?? 'Active',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateVoucher(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:60'],
            'type' => ['sometimes', 'nullable', 'in:Fixed,Percentage'],
            'discount' => ['required', 'integer', 'min:0'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'startDate' => ['sometimes', 'nullable', 'date'],
            'endDate' => ['sometimes', 'nullable', 'date'],
            'allProducts' => ['sometimes', 'boolean'],
            'oncePerCustomer' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function voucherAttributes(array $validated, Request $request): array
    {
        return [
            'name' => $validated['name'],
            'code' => $validated['code'],
            'type' => $validated['type'] ?? 'Fixed',
            'discount' => (int) $validated['discount'],
            'limit' => $validated['limit'] ?? null,
            'start_date' => $request->date('startDate')?->toDateString(),
            'end_date' => $request->date('endDate')?->toDateString(),
            'all_products' => (bool) ($validated['allProducts'] ?? true),
            'once_per_customer' => (bool) ($validated['oncePerCustomer'] ?? false),
            'status' => $validated['status'] ?? 'Active',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDiscount(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'planId' => ['sometimes', 'nullable', 'integer', 'exists:discount_plans,id'],
            'value' => ['required', 'integer', 'min:0'],
            'type' => ['sometimes', 'nullable', 'in:Fixed,Percentage'],
            'validFrom' => ['sometimes', 'nullable', 'date'],
            'validTill' => ['sometimes', 'nullable', 'date'],
            'days' => ['sometimes', 'nullable', 'array'],
            'products' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function discountAttributes(array $validated, Request $request): array
    {
        return [
            'discount_plan_id' => $validated['planId'] ?? null,
            'name' => $validated['name'],
            'value' => (int) $validated['value'],
            'type' => $validated['type'] ?? 'Fixed',
            'valid_from' => $request->date('validFrom')?->toDateString(),
            'valid_till' => $request->date('validTill')?->toDateString(),
            'days' => $validated['days'] ?? null,
            'products' => $validated['products'] ?? null,
            'status' => $validated['status'] ?? 'Active',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function couponPayload(Coupon $coupon): array
    {
        return [
            'id' => (string) $coupon->id,
            'name' => $coupon->name,
            'code' => $coupon->code,
            'type' => $coupon->type?->value,
            'discount' => (int) $coupon->discount,
            'limit' => $coupon->limit,
            'used' => (int) $coupon->used,
            'validFrom' => $coupon->valid_from?->toDateString(),
            'validUntil' => $coupon->valid_until?->toDateString(),
            'status' => $coupon->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function voucherPayload(Voucher $voucher): array
    {
        return [
            'id' => (string) $voucher->id,
            'name' => $voucher->name,
            'code' => $voucher->code,
            'type' => $voucher->type?->value,
            'discount' => (int) $voucher->discount,
            'limit' => $voucher->limit,
            'used' => (int) $voucher->used,
            'startDate' => $voucher->start_date?->toDateString(),
            'endDate' => $voucher->end_date?->toDateString(),
            'allProducts' => (bool) $voucher->all_products,
            'oncePerCustomer' => (bool) $voucher->once_per_customer,
            'status' => $voucher->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function planPayload(DiscountPlan $plan): array
    {
        return [
            'id' => (string) $plan->id,
            'name' => $plan->name,
            'customers' => $plan->customers ?? [],
            'discountCount' => (int) ($plan->discounts_count ?? 0),
            'status' => $plan->status,
        ];
    }
}
