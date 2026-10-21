<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Services\MasterDataService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    public function departments(AdminIndexRequest $request): JsonResponse
    {
        return $this->listing($request, Department::class, 'department_id');
    }

    public function designations(AdminIndexRequest $request): JsonResponse
    {
        return $this->listing($request, Designation::class, 'designation_id');
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        return $this->save($request, Department::class, 'department_id');
    }

    public function updateDepartment(Request $request, Department $department): JsonResponse
    {
        return $this->save($request, Department::class, 'department_id', $department);
    }

    public function storeDesignation(Request $request): JsonResponse
    {
        return $this->save($request, Designation::class, 'designation_id');
    }

    public function updateDesignation(Request $request, Designation $designation): JsonResponse
    {
        return $this->save($request, Designation::class, 'designation_id', $designation);
    }

    public function destroyDepartment(Department $department): JsonResponse
    {
        MasterDataService::assertDeletable($department);
        $department->delete();

        return ApiResponse::success(['id' => (string) $department->id, 'removed' => true]);
    }

    public function destroyDesignation(Designation $designation): JsonResponse
    {
        MasterDataService::assertDeletable($designation);
        $designation->delete();

        return ApiResponse::success(['id' => (string) $designation->id, 'removed' => true]);
    }

    /** @param class-string<Department|Designation> $class */
    private function listing(Request $request, string $class, string $foreignKey): JsonResponse
    {
        $query = $class::query()->with('employees:id,name,'.$foreignKey);
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.trim($request->input('search')).'%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status') === 'Disable' ? 'Inactive' : $request->input('status'));
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (Model $model) => $this->payload($model)));
    }

    /** @param class-string<Department|Designation> $class */
    private function save(Request $request, string $class, string $foreignKey, ?Model $model = null): JsonResponse
    {
        if ($request->input('status') === 'Disable') {
            $request->merge(['status' => 'Inactive']);
        }
        $validated = $request->validate([
            'id' => ['sometimes', 'nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
            'memberIds' => ['sometimes', 'array'],
            'memberIds.*' => ['required', 'integer', 'distinct', 'exists:employees,id'],
            'members' => ['sometimes', 'array'],
            'members.*' => ['required', 'string', 'max:255'],
        ]);
        if ($model === null && ! empty($validated['id'])) {
            $model = $class::query()->findOrFail($validated['id']);
        }
        $creating = $model === null;
        $model ??= new $class;
        MasterDataService::unique($model->getTable(), 'name', $validated['name'], 'name', $model->id);
        $ids = $validated['memberIds'] ?? null;
        if ($ids === null && array_key_exists('members', $validated)) {
            $ids = array_map(fn (string $name) => MasterDataService::resolve(Employee::class, null, $name, 'members'), $validated['members']);
        }
        DB::transaction(function () use ($model, $validated, $foreignKey, $ids): void {
            $model->forceFill(['name' => trim($validated['name']), 'status' => $validated['status'] ?? $model->status ?? 'Active'])->save();
            if ($ids !== null) {
                Employee::query()->where($foreignKey, $model->id)->whereNotIn('id', $ids)->update([$foreignKey => null]);
                Employee::query()->whereIn('id', $ids)->update([$foreignKey => $model->id]);
            }
        });

        return ApiResponse::success($this->payload($model->load('employees:id,name,'.$foreignKey)), [], $creating ? 201 : 200);
    }

    private function payload(Model $model): array
    {
        return [
            'id' => (string) $model->id, 'name' => $model->name, 'status' => $model->status,
            'memberIds' => $model->employees->map(fn (Employee $employee) => (string) $employee->id)->values()->all(),
            'members' => $model->employees->pluck('name')->all(), 'totalMembers' => $model->employees->count(),
            'employeeCount' => $model->employees->count(), 'createdDate' => $model->created_at?->toDateString(),
            'createdOn' => $model->created_at?->toDateString(),
        ];
    }
}
