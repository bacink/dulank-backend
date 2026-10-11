<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminIndexRequest;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Incentive;
use App\Models\Payslip;
use App\Services\PaymentService;
use App\Services\PayrollService;
use App\Support\ApiResponse;
use App\Support\DocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function salaries(AdminIndexRequest $request): JsonResponse
    {
        $query = EmployeeSalary::query()->with(['employee', 'allowances']);

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->whereHas('employee', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'));
        }

        return ApiResponse::success($query->get()->map(fn (EmployeeSalary $salary) => [
            'id' => (string) $salary->id,
            'employeeId' => (string) $salary->employee_id,
            'employee' => $salary->employee?->name,
            'name' => $salary->name,
            'salary' => (int) $salary->salary,
            'system' => $salary->system,
            'allowanceTotal' => (int) $salary->allowance_total,
            'overtimeRate' => (int) $salary->overtime_rate,
            'status' => $salary->status,
            'allowances' => $salary->allowances->map(fn ($allowance) => [
                'id' => (string) $allowance->id,
                'name' => $allowance->name,
                'amount' => (int) $allowance->amount,
            ])->values(),
        ]));
    }

    public function storeSalary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employeeId' => ['required', 'integer', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:255'],
            'salary' => ['required', 'integer', 'min:0'],
            'system' => ['sometimes', 'nullable', 'in:Monthly,Weekly,Daily'],
            'overtimeRate' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'nullable', 'in:Active,Inactive'],
            'allowances' => ['sometimes', 'nullable', 'array'],
            'allowances.*.name' => ['required', 'string', 'max:255'],
            'allowances.*.amount' => ['required', 'integer', 'min:0'],
        ]);

        $salary = EmployeeSalary::query()->updateOrCreate(
            ['employee_id' => (int) $validated['employeeId']],
            [
                'name' => $validated['name'],
                'salary' => (int) $validated['salary'],
                'system' => $validated['system'] ?? 'Monthly',
                'overtime_rate' => (int) ($validated['overtimeRate'] ?? 0),
                'status' => $validated['status'] ?? 'Active',
            ],
        );

        $salary->allowances()->delete();
        $allowanceTotal = 0;
        foreach ((array) ($validated['allowances'] ?? []) as $allowance) {
            $salary->allowances()->create([
                'name' => $allowance['name'],
                'amount' => (int) $allowance['amount'],
            ]);
            $allowanceTotal += (int) $allowance['amount'];
        }
        $salary->forceFill(['allowance_total' => $allowanceTotal])->save();

        return ApiResponse::success([
            'id' => (string) $salary->id,
            'employeeId' => (string) $salary->employee_id,
            'salary' => (int) $salary->salary,
            'allowanceTotal' => $allowanceTotal,
        ], [], 201);
    }

    public function payslips(AdminIndexRequest $request): JsonResponse
    {
        $query = Payslip::query()->with(['employee', 'items']);

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(fn (Builder $query) => $query
                ->where('slip_no', 'like', '%'.$search.'%')
                ->orWhereHas('employee', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%')));
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $payslips = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($payslips->map(fn (Payslip $payslip) => $this->payslipPayload($payslip))->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function generatePayslip(Request $request, PayrollService $payroll): JsonResponse
    {
        $validated = $request->validate([
            'employeeId' => ['required', 'integer', 'exists:employees,id'],
            'period' => ['required', 'string', 'max:20'],
            'dayWorked' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:31'],
            'overtime' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'deduction' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $employee = Employee::query()->findOrFail((int) $validated['employeeId']);

        $payslip = $payroll->generate(
            $employee,
            (string) $validated['period'],
            isset($validated['dayWorked']) ? (float) $validated['dayWorked'] : null,
            (int) ($validated['overtime'] ?? 0),
            (int) ($validated['deduction'] ?? 0),
        );

        return ApiResponse::success($this->payslipPayload($payslip), [], 201);
    }

    public function payPayslip(Request $request, string $slipNo, PaymentService $payments): JsonResponse
    {
        $payslip = Payslip::query()->where('slip_no', $slipNo)->firstOrFail();

        if ($payslip->status === 'Paid') {
            return ApiResponse::error('Payslip sudah dibayar.', 409);
        }

        $validated = $request->validate([
            'amount' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'method' => ['sometimes', 'nullable', 'string', 'max:40'],
            'date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'bankAccountId' => ['sometimes', 'nullable', 'integer', 'exists:bank_accounts,id'],
        ]);

        $payments->record(
            $request->user(),
            $payslip,
            (int) ($validated['amount'] ?? $payslip->total),
            (string) ($validated['method'] ?? 'Transfer'),
            $request->date('date')?->toDateString(),
            $validated['notes'] ?? null,
            PaymentDirection::Out,
            isset($validated['bankAccountId']) ? (int) $validated['bankAccountId'] : null,
        );

        return ApiResponse::success($this->payslipPayload($payslip->refresh()->load(['employee', 'items'])));
    }

    public function incentives(AdminIndexRequest $request): JsonResponse
    {
        $query = Incentive::query()->with('employee');

        $status = trim((string) $request->input('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $limit = $request->limit();
        $page = $request->page();
        $total = (clone $query)->count();
        $incentives = $query->orderByDesc('id')->forPage($page, $limit)->get();

        return ApiResponse::success($incentives->map(fn (Incentive $incentive) => [
            'id' => (string) $incentive->id,
            'code' => $incentive->code,
            'employeeId' => (string) $incentive->employee_id,
            'employee' => $incentive->employee?->name,
            'period' => $incentive->period,
            'qtyComplete' => (float) $incentive->qty_complete,
            'totalAmount' => (int) $incentive->total_amount,
            'status' => $incentive->status,
        ])->values(), [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }

    public function storeIncentive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employeeId' => ['required', 'integer', 'exists:employees,id'],
            'period' => ['required', 'string', 'max:20'],
            'qtyComplete' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'totalAmount' => ['required', 'integer', 'min:0'],
        ]);

        $incentive = Incentive::query()->create([
            'code' => DocumentNumber::next('incentive', Incentive::query(), 'code', 4),
            'employee_id' => (int) $validated['employeeId'],
            'period' => $validated['period'],
            'qty_complete' => (float) ($validated['qtyComplete'] ?? 0),
            'total_amount' => (int) $validated['totalAmount'],
            'status' => 'Pending',
        ]);

        return ApiResponse::success([
            'id' => (string) $incentive->id,
            'code' => $incentive->code,
            'status' => $incentive->status,
        ], [], 201);
    }

    public function payIncentive(Incentive $incentive): JsonResponse
    {
        if ($incentive->status === 'Paid') {
            return ApiResponse::error('Insentif sudah dibayar.', 409);
        }

        $incentive->forceFill(['status' => 'Paid'])->save();

        return ApiResponse::success([
            'id' => (string) $incentive->id,
            'code' => $incentive->code,
            'status' => $incentive->status,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payslipPayload(Payslip $payslip): array
    {
        return [
            'id' => (string) $payslip->id,
            'slipNo' => $payslip->slip_no,
            'employeeId' => (string) $payslip->employee_id,
            'employee' => $payslip->employee?->name,
            'period' => $payslip->period,
            'salaryRate' => (int) $payslip->salary_rate,
            'dayWorked' => (float) $payslip->day_worked,
            'allowance' => (int) $payslip->allowance,
            'overtime' => (int) $payslip->overtime,
            'deduction' => (int) $payslip->deduction,
            'total' => (int) $payslip->total,
            'status' => $payslip->status,
            'paidDate' => $payslip->paid_date?->toDateString(),
            'items' => $payslip->items->map(fn ($item) => [
                'label' => $item->label,
                'qty' => (float) $item->qty,
                'rate' => (int) $item->rate,
                'amount' => (int) $item->amount,
            ])->values(),
        ];
    }

    public function destroySalary(EmployeeSalary $salary): JsonResponse
    {
        $salary->delete();

        return ApiResponse::success(['id' => (string) $salary->id, 'removed' => true]);
    }
}
