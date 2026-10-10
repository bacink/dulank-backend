<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(AdminIndexRequest $request): JsonResponse
    {
        $query = Employee::query()->with(['department', 'designation']);

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $department = trim((string) $request->input('department', ''));
        if ($department !== '') {
            $query->whereHas('department', fn (Builder $query) => $query->where('name', $department));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn (Builder $query) => $query
                ->where('name', 'like', $term)
                ->orWhere('employee_code', 'like', $term)
                ->orWhere('email', 'like', $term));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $employees = $query->orderBy('name')->forPage($page, $limit)->get();

        return ApiResponse::success($employees->map(fn (Employee $employee) => $this->payload($employee))->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $employee = Employee::query()->create([
            'employee_code' => $validated['employeeCode'] ?? DocumentNumber::next('employee_code', Employee::query(), 'employee_code', 4),
            'name' => $validated['name'],
            'department_id' => $this->departmentId($validated['department'] ?? null),
            'designation_id' => $this->designationId($validated['designation'] ?? null),
            'address' => $validated['address'] ?? null,
            'detail_address' => $validated['detailAddress'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'join_date' => $request->date('joinDate')?->toDateString() ?? now()->toDateString(),
            'status' => $validated['status'] ?? 'Active',
            'gender' => $validated['gender'] ?? null,
            'dob' => $request->date('dob')?->toDateString(),
            'join_channel' => $validated['joinChannel'] ?? null,
            'emergency_contact_name' => $validated['emergencyContactName'] ?? null,
            'emergency_contact_phone' => $validated['emergencyContactPhone'] ?? null,
            'email' => $validated['email'] ?? null,
            'avatar_path' => $validated['avatar'] ?? null,
            'photo_id_path' => $validated['photoId'] ?? null,
        ]);

        return ApiResponse::success($this->payload($employee->load(['department', 'designation'])), [], 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        return ApiResponse::success($this->payload($employee->load(['department', 'designation', 'salary.allowances']), true));
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $this->validatePayload($request, false);

        $employee->forceFill([
            'employee_code' => $validated['employeeCode'] ?? $employee->employee_code,
            'name' => $validated['name'],
            'department_id' => $this->departmentId($validated['department'] ?? null) ?? $employee->department_id,
            'designation_id' => $this->designationId($validated['designation'] ?? null) ?? $employee->designation_id,
            'address' => $validated['address'] ?? null,
            'detail_address' => $validated['detailAddress'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'join_date' => $request->date('joinDate')?->toDateString() ?? $employee->join_date?->toDateString(),
            'status' => $validated['status'] ?? $employee->status,
            'gender' => $validated['gender'] ?? null,
            'dob' => $request->date('dob')?->toDateString() ?? $employee->dob?->toDateString(),
            'join_channel' => $validated['joinChannel'] ?? null,
            'emergency_contact_name' => $validated['emergencyContactName'] ?? null,
            'emergency_contact_phone' => $validated['emergencyContactPhone'] ?? null,
            'email' => $validated['email'] ?? null,
            'avatar_path' => $validated['avatar'] ?? $employee->avatar_path,
            'photo_id_path' => $validated['photoId'] ?? $employee->photo_id_path,
        ])->save();

        return ApiResponse::success($this->payload($employee->refresh()->load(['department', 'designation'])));
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return ApiResponse::success(['id' => (string) $employee->id, 'removed' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'employeeCode' => ['sometimes', 'nullable', 'string', 'max:60'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'detailAddress' => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'joinDate' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'in:Active,Resign,Inactive'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:20'],
            'dob' => ['sometimes', 'nullable', 'date'],
            'joinChannel' => ['sometimes', 'nullable', 'string', 'max:60'],
            'emergencyContactName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergencyContactPhone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'avatar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'photoId' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }

    private function departmentId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return Department::query()->firstOrCreate(['name' => trim($name)])->id;
    }

    private function designationId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return Designation::query()->firstOrCreate(['name' => trim($name)])->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Employee $employee, bool $withSalary = false): array
    {
        $payload = [
            'id' => (string) $employee->id,
            'employeeCode' => $employee->employee_code,
            'name' => $employee->name,
            'department' => $employee->department?->name,
            'designation' => $employee->designation?->name,
            'address' => $employee->address,
            'detailAddress' => $employee->detail_address,
            'phone' => $employee->phone,
            'joinDate' => $employee->join_date?->toDateString(),
            'status' => $employee->status,
            'gender' => $employee->gender,
            'dob' => $employee->dob?->toDateString(),
            'joinChannel' => $employee->join_channel,
            'emergencyContactName' => $employee->emergency_contact_name,
            'emergencyContactPhone' => $employee->emergency_contact_phone,
            'email' => $employee->email,
        ];

        if ($withSalary) {
            $payload['salary'] = $employee->salary === null ? null : [
                'id' => (string) $employee->salary->id,
                'name' => $employee->salary->name,
                'salary' => (int) $employee->salary->salary,
                'system' => $employee->salary->system,
                'allowanceTotal' => (int) $employee->salary->allowance_total,
                'overtimeRate' => (int) $employee->salary->overtime_rate,
                'allowances' => $employee->salary->allowances->map(fn ($allowance) => [
                    'id' => (string) $allowance->id,
                    'name' => $allowance->name,
                    'amount' => (int) $allowance->amount,
                ])->values(),
            ];
        }

        return $payload;
    }
}
