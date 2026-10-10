<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\DieCuttingBlade;
use App\Models\Machine;
use App\Models\PaperGroup;
use App\Models\PaperPrice;
use App\Models\PaperSize;
use App\Models\PaperType;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paper Shop & Machines (self + vendor).
 */
class PaperController extends Controller
{
    public function groups(AdminIndexRequest $request): JsonResponse
    {
        $query = PaperGroup::query()->withCount(['types', 'prices', 'sizes']);

        $this->applySearch($query, $request, ['name', 'merk', 'source']);

        $owner = trim((string) $request->input('owner', ''));
        if (in_array($owner, ['self', 'vendor'], true)) {
            $query->where('owner_type', $owner);
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (PaperGroup $group) => [
            'id' => (string) $group->id,
            'name' => $group->name,
            'merk' => $group->merk,
            'priceType' => $group->price_type,
            'source' => $group->source,
            'owner' => $group->owner_type?->value,
            'typeCount' => $group->types_count,
            'update' => $group->updated_at?->toDateString(),
            'status' => $group->status,
        ]));
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'merk' => ['sometimes', 'nullable', 'string', 'max:255'],
            'priceType' => ['sometimes', 'nullable', 'in:Yes,No'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner' => ['sometimes', 'nullable', 'in:self,vendor'],
            'status' => ['sometimes', 'nullable', 'in:Active,Deactive,Inactive'],
        ]);

        $group = PaperGroup::query()->create([
            'owner_type' => $validated['owner'] ?? 'self',
            'source' => $validated['source'] ?? null,
            'name' => $validated['name'],
            'merk' => $validated['merk'] ?? null,
            'price_type' => $validated['priceType'] ?? 'No',
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success(['id' => (string) $group->id, 'name' => $group->name], [], 201);
    }

    public function updateGroup(Request $request, PaperGroup $paperGroup): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'merk' => ['sometimes', 'nullable', 'string', 'max:255'],
            'priceType' => ['sometimes', 'nullable', 'in:Yes,No'],
            'status' => ['sometimes', 'nullable', 'in:Active,Deactive,Inactive'],
        ]);

        $paperGroup->forceFill([
            'name' => $validated['name'],
            'merk' => $validated['merk'] ?? null,
            'price_type' => $validated['priceType'] ?? $paperGroup->price_type,
            'status' => $validated['status'] ?? $paperGroup->status,
        ])->save();

        return ApiResponse::success(['id' => (string) $paperGroup->id, 'name' => $paperGroup->name]);
    }

    public function destroyGroup(PaperGroup $paperGroup): JsonResponse
    {
        $paperGroup->delete();

        return ApiResponse::success(['id' => (string) $paperGroup->id, 'removed' => true]);
    }

    public function items(AdminIndexRequest $request): JsonResponse
    {
        $query = PaperType::query()->with('group');

        $this->applySearch($query, $request, ['name', 'merk']);

        $group = trim((string) $request->input('group', ''));
        if ($group !== '') {
            $query->whereHas('group', fn (Builder $query) => $query->where('name', $group));
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (PaperType $type) => $this->itemPayload($type)));
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(PaperType $type): array
    {
        return [
            'id' => (string) $type->id,
            'groupId' => $type->paper_group_id !== null ? (string) $type->paper_group_id : null,
            'name' => $type->name,
            'merk' => $type->merk,
            'price' => (int) $type->price,
            'priceType' => 'Group',
            'unitPrice' => $type->unit,
            'gsm' => $type->gsm !== null ? (float) $type->gsm : null,
            'stock' => (float) $type->stock,
            'unitStock' => $type->unit_stock,
            'update' => $type->updated_at?->toDateString(),
            'status' => $type->status,
        ];
    }

    public function storeItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'groupId' => ['sometimes', 'nullable', 'integer', 'exists:paper_groups,id'],
            'name' => ['required', 'string', 'max:255'],
            'merk' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'unitPrice' => ['sometimes', 'nullable', 'string', 'max:30'],
            'gsm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unitStock' => ['sometimes', 'nullable', 'string', 'max:30'],
            'status' => ['sometimes', 'nullable', 'in:Active,Deactive,Inactive'],
        ]);

        $type = PaperType::query()->create($this->itemAttributes($validated));

        return ApiResponse::success($this->itemPayload($type), [], 201);
    }

    public function updateItem(Request $request, PaperType $paperItem): JsonResponse
    {
        $validated = $request->validate([
            'groupId' => ['sometimes', 'nullable', 'integer', 'exists:paper_groups,id'],
            'name' => ['required', 'string', 'max:255'],
            'merk' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'unitPrice' => ['sometimes', 'nullable', 'string', 'max:30'],
            'gsm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stock' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unitStock' => ['sometimes', 'nullable', 'string', 'max:30'],
            'status' => ['sometimes', 'nullable', 'in:Active,Deactive,Inactive'],
        ]);

        $paperItem->forceFill($this->itemAttributes($validated))->save();

        return ApiResponse::success($this->itemPayload($paperItem->refresh()));
    }

    public function destroyItem(PaperType $paperItem): JsonResponse
    {
        $paperItem->delete();

        return ApiResponse::success(['id' => (string) $paperItem->id, 'removed' => true]);
    }

    public function sizes(AdminIndexRequest $request): JsonResponse
    {
        $query = PaperSize::query();

        $this->applySearch($query, $request, ['name']);

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (PaperSize $size) => [
            'id' => (string) $size->id,
            'name' => $size->name,
            'dimension' => $size->dimension,
            'length' => $size->length !== null ? (float) $size->length : null,
            'width' => $size->width !== null ? (float) $size->width : null,
            'unit' => $size->unit,
            'update' => $size->updated_at?->toDateString(),
            'status' => $size->status,
        ]));
    }

    public function storeSize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'dimension' => ['sometimes', 'nullable', 'string', 'max:120'],
            'length' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'nullable', 'in:Active,Deactive,Inactive'],
        ]);

        $size = PaperSize::query()->create([
            'name' => $validated['name'],
            'dimension' => $validated['dimension'] ?? null,
            'length' => isset($validated['length']) ? (float) $validated['length'] : null,
            'width' => isset($validated['width']) ? (float) $validated['width'] : null,
            'unit' => $validated['unit'] ?? null,
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success(['id' => (string) $size->id, 'name' => $size->name], [], 201);
    }

    public function updateSize(Request $request, PaperSize $paperSize): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'dimension' => ['sometimes', 'nullable', 'string', 'max:120'],
            'length' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:20'],
            'status' => ['sometimes', 'nullable', 'in:Active,Deactive,Inactive'],
        ]);

        $paperSize->forceFill([
            'name' => $validated['name'],
            'dimension' => $validated['dimension'] ?? null,
            'length' => isset($validated['length']) ? (float) $validated['length'] : null,
            'width' => isset($validated['width']) ? (float) $validated['width'] : null,
            'unit' => $validated['unit'] ?? null,
            'status' => $validated['status'] ?? $paperSize->status,
        ])->save();

        return ApiResponse::success(['id' => (string) $paperSize->id, 'name' => $paperSize->name]);
    }

    public function destroySize(PaperSize $paperSize): JsonResponse
    {
        $paperSize->delete();

        return ApiResponse::success(['id' => (string) $paperSize->id, 'removed' => true]);
    }

    public function prices(AdminIndexRequest $request): JsonResponse
    {
        $query = PaperPrice::query()->with('group');

        $this->applySearch($query, $request, ['name']);

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (PaperPrice $price) => $this->pricePayload($price)));
    }

    /**
     * @return array<string, mixed>
     */
    private function pricePayload(PaperPrice $price): array
    {
        return [
            'id' => (string) $price->id,
            'nama' => $price->name,
            'group' => $price->group?->name,
            'merk' => $price->group?->merk,
            'minOrder' => $price->min_order !== null ? (string) $price->min_order : null,
            'kelipatan' => $price->multiple !== null ? (string) $price->multiple : null,
            'harga' => (int) $price->price,
            'update' => $price->updated_at?->toDateString(),
            'status' => $price->status,
        ];
    }

    public function storePrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'group' => ['sometimes', 'nullable', 'string', 'max:255'],
            'harga' => ['required', 'integer', 'min:0'],
            'minOrder' => ['sometimes', 'nullable', 'string', 'max:60'],
            'kelipatan' => ['sometimes', 'nullable', 'string', 'max:60'],
            'status' => ['sometimes', 'nullable', 'in:Active,Deactive,Inactive'],
        ]);

        $price = PaperPrice::query()->create($this->priceAttributes($validated));

        return ApiResponse::success($this->pricePayload($price->load('group')), [], 201);
    }

    public function updatePrice(Request $request, PaperPrice $paperPrice): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'group' => ['sometimes', 'nullable', 'string', 'max:255'],
            'harga' => ['required', 'integer', 'min:0'],
            'minOrder' => ['sometimes', 'nullable', 'string', 'max:60'],
            'kelipatan' => ['sometimes', 'nullable', 'string', 'max:60'],
            'status' => ['sometimes', 'nullable', 'in:Active,Deactive,Inactive'],
        ]);

        $paperPrice->forceFill($this->priceAttributes($validated))->save();

        return ApiResponse::success($this->pricePayload($paperPrice->refresh()->load('group')));
    }

    public function destroyPrice(PaperPrice $paperPrice): JsonResponse
    {
        $paperPrice->delete();

        return ApiResponse::success(['id' => (string) $paperPrice->id, 'removed' => true]);
    }

    public function machines(AdminIndexRequest $request): JsonResponse
    {
        $query = Machine::query();

        $this->applySearch($query, $request, ['name', 'source']);

        $kind = trim((string) $request->input('kind', ''));
        if ($kind !== '') {
            $query->where('kind', $kind);
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (Machine $machine) => $this->machinePayload($machine)));
    }

    /**
     * @return array<string, mixed>
     */
    private function machinePayload(Machine $machine): array
    {
        return [
            'id' => (string) $machine->id,
            'kind' => $machine->kind?->value,
            'type' => $machine->type,
            'name' => $machine->name,
            'colors' => $machine->colors,
            'maxArea' => $machine->max_area,
            'plateCost' => $machine->plate_cost,
            'minim' => $machine->minim,
            'druck' => $machine->druck,
            'size' => $machine->size,
            'price' => $machine->price,
            'unitPrice' => $machine->unit_price,
            'source' => $machine->source,
            'owner' => $machine->owner_type?->value,
            'published' => (bool) $machine->published,
            'active' => (bool) $machine->active,
            'update' => $machine->updated_at?->toDateString(),
            'status' => $machine->status,
        ];
    }

    public function storeMachine(Request $request): JsonResponse
    {
        $validated = $this->validateMachine($request);

        $machine = Machine::query()->create($this->machineAttributes($validated));

        return ApiResponse::success($this->machinePayload($machine), [], 201);
    }

    public function updateMachine(Request $request, Machine $printingMachine): JsonResponse
    {
        $validated = $this->validateMachine($request);

        $printingMachine->forceFill($this->machineAttributes($validated))->save();

        return ApiResponse::success($this->machinePayload($printingMachine->refresh()));
    }

    public function destroyMachine(Machine $printingMachine): JsonResponse
    {
        $printingMachine->delete();

        return ApiResponse::success(['id' => (string) $printingMachine->id, 'removed' => true]);
    }

    public function blades(AdminIndexRequest $request): JsonResponse
    {
        $query = DieCuttingBlade::query();

        $this->applySearch($query, $request, ['name']);

        return ApiResponse::success($query->orderBy('name')->get()->map(fn ($blade) => $this->bladePayload($blade)));
    }

    /**
     * @return array<string, mixed>
     */
    private function bladePayload(DieCuttingBlade $blade): array
    {
        return [
            'id' => (string) $blade->id,
            'name' => $blade->name,
            'pricePerCm' => (int) $blade->price_per_cm,
            'published' => (bool) $blade->published,
            'active' => (bool) $blade->active,
            'update' => $blade->updated_at?->toDateString(),
        ];
    }

    public function storeBlade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pricePerCm' => ['required', 'integer', 'min:0'],
            'published' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $blade = DieCuttingBlade::query()->create([
            'name' => $validated['name'],
            'price_per_cm' => (int) $validated['pricePerCm'],
            'published' => (bool) ($validated['published'] ?? true),
            'active' => (bool) ($validated['active'] ?? true),
        ]);

        return ApiResponse::success($this->bladePayload($blade), [], 201);
    }

    public function updateBlade(Request $request, DieCuttingBlade $dieCuttingBlade): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pricePerCm' => ['required', 'integer', 'min:0'],
            'published' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $dieCuttingBlade->forceFill([
            'name' => $validated['name'],
            'price_per_cm' => (int) $validated['pricePerCm'],
            'published' => (bool) ($validated['published'] ?? $dieCuttingBlade->published),
            'active' => (bool) ($validated['active'] ?? $dieCuttingBlade->active),
        ])->save();

        return ApiResponse::success($this->bladePayload($dieCuttingBlade->refresh()));
    }

    public function destroyBlade(DieCuttingBlade $dieCuttingBlade): JsonResponse
    {
        $dieCuttingBlade->delete();

        return ApiResponse::success(['id' => (string) $dieCuttingBlade->id, 'removed' => true]);
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
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function itemAttributes(array $validated): array
    {
        return [
            'paper_group_id' => $validated['groupId'] ?? null,
            'source' => 'self',
            'name' => $validated['name'],
            'merk' => $validated['merk'] ?? null,
            'price' => (int) ($validated['price'] ?? 0),
            'unit' => $validated['unitPrice'] ?? null,
            'gsm' => isset($validated['gsm']) ? (float) $validated['gsm'] : null,
            'stock' => (float) ($validated['stock'] ?? 0),
            'unit_stock' => $validated['unitStock'] ?? null,
            'published' => true,
            'active' => true,
            'status' => $validated['status'] ?? 'Active',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function priceAttributes(array $validated): array
    {
        $group = null;
        if (! empty($validated['group'])) {
            $group = PaperGroup::query()->firstOrCreate(['name' => $validated['group']], ['owner_type' => 'self']);
        }

        return [
            'paper_group_id' => $group?->id,
            'source' => $group?->source ?? 'self',
            'name' => $validated['nama'],
            'min_order' => isset($validated['minOrder']) ? (float) $validated['minOrder'] : null,
            'multiple' => isset($validated['kelipatan']) ? (float) $validated['kelipatan'] : null,
            'price' => (int) $validated['harga'],
            'published' => true,
            'active' => true,
            'status' => $validated['status'] ?? 'Active',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMachine(Request $request): array
    {
        return $request->validate([
            'kind' => ['sometimes', 'nullable', 'in:printing,lamination,die-cutting,foil'],
            'type' => ['sometimes', 'nullable', 'in:Offset,Digital Printing,Digital Print'],
            'name' => ['required', 'string', 'max:255'],
            'colors' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'maxArea' => ['sometimes', 'nullable', 'string', 'max:120'],
            'plateCost' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'minim' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'druck' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'size' => ['sometimes', 'nullable', 'string', 'max:120'],
            'price' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'unitPrice' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'owner' => ['sometimes', 'nullable', 'in:self,vendor'],
            'published' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive,Deactive'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function machineAttributes(array $validated): array
    {
        return [
            'owner_type' => $validated['owner'] ?? 'self',
            'kind' => $validated['kind'] ?? 'printing',
            'source' => $validated['source'] ?? null,
            'name' => $validated['name'],
            'type' => $validated['type'] ?? null,
            'colors' => $validated['colors'] ?? null,
            'max_area' => $validated['maxArea'] ?? null,
            'plate_cost' => isset($validated['plateCost']) ? (int) $validated['plateCost'] : null,
            'minim' => isset($validated['minim']) ? (int) $validated['minim'] : null,
            'druck' => isset($validated['druck']) ? (int) $validated['druck'] : null,
            'size' => $validated['size'] ?? null,
            'price' => isset($validated['price']) ? (int) $validated['price'] : null,
            'unit_price' => isset($validated['unitPrice']) ? (int) $validated['unitPrice'] : null,
            'published' => (bool) ($validated['published'] ?? true),
            'active' => (bool) ($validated['active'] ?? true),
            'status' => $validated['status'] ?? 'Active',
        ];
    }
}
