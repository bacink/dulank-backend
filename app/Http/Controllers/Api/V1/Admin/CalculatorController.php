<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\CalculatorComponent;
use App\Models\Listing;
use App\Models\ModerationHistory;
use App\Models\Partner;
use App\Models\PartnerMetric;
use App\Models\ProfitTier;
use App\Models\RequestQuotation;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Calculator: komponen (fiks/minimum/jasa lain), profit tier, partner/listings marketplace, dashboard.
 */
class CalculatorController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const KINDS = [
        'komponen-fiks' => 'fixed',
        'komponen-minimum' => 'minimum',
        'jasa-lain' => 'jasa_lain',
    ];

    public function components(AdminIndexRequest $request, string $kind): JsonResponse
    {
        $query = CalculatorComponent::query()->where('kind', $this->kind($kind));
        $this->applySearch($query, $request, ['name']);

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (CalculatorComponent $component) => $this->componentPayload($component))->values());
    }

    public function storeComponent(Request $request, string $kind): JsonResponse
    {
        $validated = $this->validateComponent($request);

        $component = CalculatorComponent::query()->create([
            'kind' => $this->kind($kind),
            'name' => $validated['name'],
            'value' => isset($validated['value']) ? (int) $validated['value'] : null,
            'rate' => isset($validated['rate']) ? (int) $validated['rate'] : (isset($validated['harga']) ? (int) $validated['harga'] : null),
            'minim' => isset($validated['minim']) ? (int) $validated['minim'] : (isset($validated['minimHarga']) ? (int) $validated['minimHarga'] : null),
            'unit' => $validated['satuan'] ?? $validated['unit'] ?? null,
            'used' => (int) ($validated['used'] ?? 0),
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success($this->componentPayload($component), [], 201);
    }

    public function updateComponent(Request $request, string $kind, CalculatorComponent $calculatorComponent): JsonResponse
    {
        $validated = $this->validateComponent($request);

        $calculatorComponent->forceFill([
            'name' => $validated['name'],
            'value' => isset($validated['value']) ? (int) $validated['value'] : null,
            'rate' => isset($validated['rate']) ? (int) $validated['rate'] : (isset($validated['harga']) ? (int) $validated['harga'] : null),
            'minim' => isset($validated['minim']) ? (int) $validated['minim'] : (isset($validated['minimHarga']) ? (int) $validated['minimHarga'] : null),
            'unit' => $validated['satuan'] ?? $validated['unit'] ?? null,
            'status' => $validated['status'] ?? $calculatorComponent->status,
        ])->save();

        return ApiResponse::success($this->componentPayload($calculatorComponent->refresh()));
    }

    public function destroyComponent(string $kind, CalculatorComponent $calculatorComponent): JsonResponse
    {
        $calculatorComponent->delete();

        return ApiResponse::success(['id' => (string) $calculatorComponent->id, 'removed' => true]);
    }

    public function profitTiers(AdminIndexRequest $request): JsonResponse
    {
        return ApiResponse::success(ProfitTier::query()->orderBy('min_qty')->get()->map(fn (ProfitTier $tier) => [
            'id' => (string) $tier->id,
            'minQty' => $tier->min_qty,
            'maxQty' => $tier->max_qty,
            'profitPosPercent' => (float) $tier->profit_pos_percent,
            'profitWebstorePercent' => (float) $tier->profit_webstore_percent,
        ]));
    }

    public function storeProfitTier(Request $request): JsonResponse
    {
        $validated = $this->validateProfitTier($request);

        $tier = ProfitTier::query()->create([
            'min_qty' => (int) $validated['minQty'],
            'max_qty' => isset($validated['maxQty']) ? (int) $validated['maxQty'] : null,
            'profit_pos_percent' => (float) $validated['profitPosPercent'],
            'profit_webstore_percent' => (float) $validated['profitWebstorePercent'],
        ]);

        return ApiResponse::success(['id' => (string) $tier->id], [], 201);
    }

    public function updateProfitTier(Request $request, ProfitTier $profitTier): JsonResponse
    {
        $validated = $this->validateProfitTier($request);

        $profitTier->forceFill([
            'min_qty' => (int) $validated['minQty'],
            'max_qty' => isset($validated['maxQty']) ? (int) $validated['maxQty'] : null,
            'profit_pos_percent' => (float) $validated['profitPosPercent'],
            'profit_webstore_percent' => (float) $validated['profitWebstorePercent'],
        ])->save();

        return ApiResponse::success(['id' => (string) $profitTier->id]);
    }

    public function destroyProfitTier(ProfitTier $profitTier): JsonResponse
    {
        $profitTier->delete();

        return ApiResponse::success(['id' => (string) $profitTier->id, 'removed' => true]);
    }

    public function partners(AdminIndexRequest $request): JsonResponse
    {
        $query = Partner::query()->with('metrics');

        $type = trim((string) $request->input('type', ''));
        if ($type !== '') {
            $query->where('type', $type);
        }

        $this->applySearch($query, $request, ['name', 'province', 'city']);

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (Partner $partner) => $this->partnerPayload($partner))->values());
    }

    public function storePartner(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:printing_shop,paper_store'],
            'name' => ['required', 'string', 'max:255'],
            'province' => ['sometimes', 'nullable', 'string', 'max:120'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'district' => ['sometimes', 'nullable', 'string', 'max:120'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $partner = Partner::query()->create([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'province' => $validated['province'] ?? null,
            'city' => $validated['city'] ?? null,
            'district' => $validated['district'] ?? null,
            'address' => $validated['address'] ?? null,
            'whatsapp' => $validated['whatsapp'] ?? null,
            'status' => $validated['status'] ?? 'Active',
        ]);

        PartnerMetric::query()->create(['partner_id' => $partner->id]);

        return ApiResponse::success($this->partnerPayload($partner->load('metrics')), [], 201);
    }

    public function updatePartner(Request $request, Partner $partner): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'province' => ['sometimes', 'nullable', 'string', 'max:120'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'district' => ['sometimes', 'nullable', 'string', 'max:120'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $partner->forceFill([
            'name' => $validated['name'],
            'province' => $validated['province'] ?? null,
            'city' => $validated['city'] ?? null,
            'district' => $validated['district'] ?? null,
            'address' => $validated['address'] ?? null,
            'whatsapp' => $validated['whatsapp'] ?? null,
            'status' => $validated['status'] ?? $partner->status,
        ])->save();

        return ApiResponse::success($this->partnerPayload($partner->refresh()->load('metrics')));
    }

    public function destroyPartner(Partner $partner): JsonResponse
    {
        $partner->delete();

        return ApiResponse::success(['id' => (string) $partner->id, 'removed' => true]);
    }

    public function moderatePartner(Request $request, Partner $partner): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive,Flagged'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $partner->forceFill([
            'status' => $validated['status'] ?? $partner->status,
        ])->save();

        return ApiResponse::success($this->partnerPayload($partner->refresh()));
    }

    public function listings(AdminIndexRequest $request): JsonResponse
    {
        $query = Listing::query()->with('partner');

        $type = trim((string) $request->input('type', ''));
        if ($type !== '') {
            $query->where('type', $type);
        }

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $listings = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($listings->map(fn (Listing $listing) => $this->listingPayload($listing))->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    /**
     * Bekukan listing + catat riwayat moderasi.
     */
    public function freezeListing(Request $request, Listing $listing): JsonResponse
    {
        $validated = $request->validate([
            'duration' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notify' => ['sometimes', 'boolean'],
            'message' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $listing->forceFill(['status' => 'Frozen', 'published' => false])->save();

        $listing->moderationHistories()->create([
            'action' => 'freeze',
            'duration' => $validated['duration'] ?? null,
            'notify' => (bool) ($validated['notify'] ?? false),
            'message' => $validated['message'] ?? null,
            'acted_by' => $request->user()->name,
            'acted_at' => now(),
        ]);

        return ApiResponse::success($this->listingPayload($listing->refresh()->load('partner')));
    }

    public function destroyListing(Listing $listing): JsonResponse
    {
        $listing->delete();

        return ApiResponse::success(['id' => (string) $listing->id, 'removed' => true]);
    }

    public function moderationHistory(AdminIndexRequest $request): JsonResponse
    {
        $query = ModerationHistory::query()->with('listing');

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($rows->map(fn (ModerationHistory $history) => [
            'id' => (string) $history->id,
            'listingId' => (string) $history->listing_id,
            'listingType' => $history->listing?->type,
            'action' => $history->action,
            'duration' => $history->duration,
            'notify' => $history->notify,
            'message' => $history->message,
            'actedBy' => $history->acted_by,
            'actedAt' => $history->acted_at?->toIso8601String(),
        ])->values(), ['page' => $page, 'limit' => $limit, 'total' => $total]);
    }

    public function dashboard(): JsonResponse
    {
        $labels = [];
        $thisWeek = [];
        $lastWeek = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $labels[] = now()->subDays($i)->format('D');
            $thisWeek[] = RequestQuotation::query()->whereDate('created_at', $day)->count();
            $lastWeek[] = Listing::query()->whereDate('created_at', $day)->count();
        }

        $users = Partner::query()
            ->withCount('listings')
            ->orderByDesc('listings_count')
            ->limit(20)
            ->get()
            ->map(fn (Partner $partner) => [
                'id' => (int) $partner->id,
                'userId' => (string) $partner->id,
                'user' => $partner->name,
                'calculate' => (int) $partner->listings_count,
                'request' => 0,
                'usage' => (int) $partner->listings_count,
                'status' => 'Active',
                'role' => 'partner',
                'lastActive' => $partner->updated_at?->toIso8601String(),
                'departmentId' => '',
                'employeeId' => '',
                'createdAt' => $partner->created_at?->toIso8601String(),
                'updatedAt' => $partner->updated_at?->toIso8601String(),
            ])->values();

        return ApiResponse::success([
            'telemetry' => [
                'currentUsers' => User::query()->where('type', 'store')->where('status', 'Active')->count(),
                'totalRequest' => RequestQuotation::query()->count(),
                'totalCalculate' => Listing::query()->count(),
                'requestGrowth' => '0%',
                'calculateGrowth' => '0%',
                'chart' => ['labels' => $labels, 'thisWeek' => $thisWeek, 'lastWeek' => $lastWeek],
            ],
            'users' => $users,
        ]);
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

    private function kind(string $kind): string
    {
        return self::KINDS[$kind] ?? 'fixed';
    }

    /**
     * @return array<string, mixed>
     */
    private function validateComponent(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'value' => ['sometimes', 'nullable', 'integer'],
            'rate' => ['sometimes', 'nullable', 'integer'],
            'minim' => ['sometimes', 'nullable', 'integer'],
            'minimHarga' => ['sometimes', 'nullable', 'integer'],
            'harga' => ['sometimes', 'nullable', 'integer'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:30'],
            'satuan' => ['sometimes', 'nullable', 'string', 'max:30'],
            'used' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'nullable', 'in:Active,Deactive,Inactive'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function componentPayload(CalculatorComponent $component): array
    {
        $base = [
            'id' => (string) $component->id,
            'name' => $component->name,
            'status' => $component->status === 'Deactive' ? 'Deactive' : $component->status,
            'updatedAt' => $component->updated_at?->toIso8601String(),
            'update' => $component->updated_at?->toDateString(),
        ];

        return match ($component->kind) {
            'minimum' => $base + [
                'rate' => (int) $component->rate,
                'minim' => (int) $component->minim,
                'unit' => $component->unit,
                'used' => (int) $component->used,
            ],
            'jasa_lain' => $base + [
                'harga' => (int) ($component->rate ?? $component->value ?? 0),
                'minimHarga' => (int) $component->minim,
                'satuan' => $component->unit,
            ],
            default => $base + [
                'value' => (int) $component->value,
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProfitTier(Request $request): array
    {
        return $request->validate([
            'minQty' => ['required', 'integer', 'min:0'],
            'maxQty' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'profitPosPercent' => ['required', 'numeric', 'min:0', 'max:100'],
            'profitWebstorePercent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function partnerPayload(Partner $partner): array
    {
        return [
            'id' => (string) $partner->id,
            'type' => $partner->type,
            'name' => $partner->name,
            'province' => $partner->province,
            'city' => $partner->city,
            'district' => $partner->district,
            'address' => $partner->address,
            'whatsapp' => $partner->whatsapp,
            'followers' => $partner->followers_label,
            'following' => (int) $partner->following_count,
            'status' => $partner->status,
            'metrics' => $partner->metrics === null ? null : [
                'paperCount' => $partner->metrics->paper_count,
                'printingCount' => $partner->metrics->printing_count,
                'laminationCount' => $partner->metrics->lamination_count,
                'dieCuttingCount' => $partner->metrics->die_cutting_count,
                'foilCount' => $partner->metrics->foil_count,
                'groupCount' => $partner->metrics->group_count,
                'typeCount' => $partner->metrics->type_count,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listingPayload(Listing $listing): array
    {
        return [
            'id' => (string) $listing->id,
            'partnerId' => (string) $listing->partner_id,
            'partner' => $listing->partner?->name,
            'type' => $listing->type,
            'payload' => $listing->payload ?? [],
            'status' => $listing->status?->value,
            'published' => (bool) $listing->published,
            'created' => $listing->created_at?->toIso8601String(),
        ];
    }
}
