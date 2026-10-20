<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function departments(AdminIndexRequest $request): JsonResponse
    {
        $query = Department::query()->withCount('employees');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->orderBy('name')->get()->map(fn (Department $department) => [
            'id' => (string) $department->id,
            'name' => $department->name,
            'status' => $department->status,
            'employeeCount' => $department->employees_count,
        ]));
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        if ($id = $request->input('id')) {
            $department = Department::query()->findOrFail($id);
            return $this->updateDepartment($request, $department);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $department = Department::query()->create([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success(['id' => (string) $department->id, 'name' => $department->name, 'status' => $department->status], [], 201);
    }

    public function updateDepartment(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $department->forceFill([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? $department->status,
        ])->save();

        return ApiResponse::success(['id' => (string) $department->id, 'name' => $department->name, 'status' => $department->status]);
    }

    public function destroyDepartment(Department $department): JsonResponse
    {
        $department->delete();

        return ApiResponse::success(['id' => (string) $department->id, 'removed' => true]);
    }

    public function designations(AdminIndexRequest $request): JsonResponse
    {
        $query = Designation::query()->withCount('employees');

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return ApiResponse::success($query->with('employees:id,designation_id,name')->orderBy('name')->get()->map(fn (Designation $designation) => [
            'id' => (string) $designation->id,
            'name' => $designation->name,
            'status' => $designation->status,
            'members' => $designation->employees->pluck('name')->all(),
            'totalMembers' => (int) $designation->employees_count,
            'createdOn' => $designation->created_at?->format('d M Y') ?? date('d M Y'),
        ]));
    }

    public function storeDesignation(Request $request): JsonResponse
    {
        if ($id = $request->input('id')) {
            $designation = Designation::query()->findOrFail($id);
            return $this->updateDesignation($request, $designation);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $designation = Designation::query()->create([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'Active',
        ]);

        return ApiResponse::success(['id' => (string) $designation->id, 'name' => $designation->name, 'status' => $designation->status], [], 201);
    }

    public function updateDesignation(Request $request, Designation $designation): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
        ]);

        $designation->forceFill([
            'name' => $validated['name'],
            'status' => $validated['status'] ?? $designation->status,
        ])->save();

        return ApiResponse::success(['id' => (string) $designation->id, 'name' => $designation->name, 'status' => $designation->status]);
    }

    public function destroyDesignation(Designation $designation): JsonResponse
    {
        $designation->delete();

        return ApiResponse::success(['id' => (string) $designation->id, 'removed' => true]);
    }
}
