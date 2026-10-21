<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Services\MasterDataService;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'user_id' => $validated['userId'] ?? null,
            'emergency_contact2_name' => $validated['contact2Name'] ?? null,
            'emergency_contact2_phone' => $validated['contact2Phone'] ?? null,
            'department_id' => $validated['departmentId'] ?? $this->departmentId($validated['department'] ?? null),
            'designation_id' => $validated['designationId'] ?? $this->designationId($validated['designation'] ?? null),
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
            'name' => $validated['name'] ?? $employee->name,
            'user_id' => array_key_exists('userId', $validated) ? $validated['userId'] : $employee->user_id,
            'emergency_contact2_name' => array_key_exists('contact2Name', $validated) ? $validated['contact2Name'] : $employee->emergency_contact2_name,
            'emergency_contact2_phone' => array_key_exists('contact2Phone', $validated) ? $validated['contact2Phone'] : $employee->emergency_contact2_phone,
            'department_id' => $validated['departmentId'] ?? $this->departmentId($validated['department'] ?? null) ?? $employee->department_id,
            'designation_id' => $validated['designationId'] ?? $this->designationId($validated['designation'] ?? null) ?? $employee->designation_id,
            'address' => array_key_exists('address', $validated) ? $validated['address'] : $employee->address,
            'detail_address' => array_key_exists('detailAddress', $validated) ? $validated['detailAddress'] : $employee->detail_address,
            'phone' => array_key_exists('phone', $validated) ? $validated['phone'] : $employee->phone,
            'join_date' => $request->date('joinDate')?->toDateString() ?? $employee->join_date?->toDateString(),
            'status' => $validated['status'] ?? $employee->status,
            'gender' => array_key_exists('gender', $validated) ? $validated['gender'] : $employee->gender,
            'dob' => $request->date('dob')?->toDateString() ?? $employee->dob?->toDateString(),
            'join_channel' => array_key_exists('joinChannel', $validated) ? $validated['joinChannel'] : $employee->join_channel,
            'emergency_contact_name' => array_key_exists('emergencyContactName', $validated) ? $validated['emergencyContactName'] : $employee->emergency_contact_name,
            'emergency_contact_phone' => array_key_exists('emergencyContactPhone', $validated) ? $validated['emergencyContactPhone'] : $employee->emergency_contact_phone,
            'email' => array_key_exists('email', $validated) ? $validated['email'] : $employee->email,
            'avatar_path' => $validated['avatar'] ?? $employee->avatar_path,
            'photo_id_path' => $validated['photoId'] ?? $employee->photo_id_path,
        ])->save();

        return ApiResponse::success($this->payload($employee->refresh()->load(['department', 'designation'])));
    }

    public function destroy(Employee $employee): JsonResponse
    {
        MasterDataService::assertDeletable($employee);
        $employee->delete();

        return ApiResponse::success(['id' => (string) $employee->id, 'removed' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $creating = true): array
    {
        $request->merge(array_filter(['emergencyContactName' => $request->input('emergencyContactName', $request->input('contact1Name')), 'emergencyContactPhone' => $request->input('emergencyContactPhone', $request->input('contact1Phone'))], fn ($value) => $value !== null));

        return $request->validate([
            'employeeCode' => ['sometimes', 'nullable', 'string', 'max:60', Rule::unique('employees', 'employee_code')->ignore($request->route('employee'))],
            'departmentId' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'designationId' => ['sometimes', 'nullable', 'integer', 'exists:designations,id'],
            'userId' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('type', 'admin'), Rule::unique('employees', 'user_id')->ignore($request->route('employee'))],
            'contact2Name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact2Phone' => ['sometimes', 'nullable', 'string', 'max:30'],
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

        return MasterDataService::resolve(Department::class, null, $name, 'departmentId');
    }

    private function designationId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        return MasterDataService::resolve(Designation::class, null, $name, 'designationId');
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
            'departmentId' => $employee->department_id ? (string) $employee->department_id : null,
            'designationId' => $employee->designation_id ? (string) $employee->designation_id : null,
            'userId' => $employee->user_id ? (string) $employee->user_id : null,
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
            'avatar' => $employee->avatar_path, 'photoId' => $employee->photo_id_path,
            'contact1Name' => $employee->emergency_contact_name, 'contact1Phone' => $employee->emergency_contact_phone,
            'contact2Name' => $employee->emergency_contact2_name, 'contact2Phone' => $employee->emergency_contact2_phone,
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
