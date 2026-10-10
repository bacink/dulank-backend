<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Employee;
use App\Models\Payslip;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Payroll: generate payslip dari salary + allowance karyawan (PRD §4.6).
 */
final class PayrollService
{
    public function generate(Employee $employee, string $period, ?float $dayWorked = null, int $overtime = 0, int $deduction = 0): Payslip
    {
        $employee->loadMissing('salary.allowances');
        $salary = $employee->salary;

        if ($salary === null) {
            throw ApiException::validation('Karyawan belum memiliki data gaji.', [
                'employeeId' => ['Karyawan belum memiliki data gaji.'],
            ]);
        }

        return DB::transaction(function () use ($employee, $salary, $period, $dayWorked, $overtime, $deduction): Payslip {
            $rate = (int) $salary->salary;
            $allowanceTotal = (int) $salary->allowances->sum('amount');
            $total = $rate + $allowanceTotal + $overtime - $deduction;

            $payslip = Payslip::query()->create([
                'slip_no' => DocumentNumber::next('payslip', Payslip::query(), 'slip_no', 3),
                'employee_id' => $employee->id,
                'period' => $period,
                'salary_rate' => $rate,
                'day_worked' => $dayWorked ?? 26,
                'allowance' => $allowanceTotal,
                'overtime' => $overtime,
                'deduction' => $deduction,
                'total' => $total,
                'status' => 'Unpaid',
            ]);

            $payslip->items()->create(['label' => 'Basic Salary', 'qty' => 1, 'rate' => $rate, 'amount' => $rate]);

            foreach ($salary->allowances as $allowance) {
                $payslip->items()->create([
                    'label' => $allowance->name,
                    'qty' => 1,
                    'rate' => (int) $allowance->amount,
                    'amount' => (int) $allowance->amount,
                ]);
            }

            if ($overtime > 0) {
                $payslip->items()->create(['label' => 'Overtime', 'qty' => 1, 'rate' => $overtime, 'amount' => $overtime]);
            }

            if ($deduction > 0) {
                $payslip->items()->create(['label' => 'Deduction', 'qty' => 1, 'rate' => $deduction, 'amount' => -$deduction]);
            }

            return $payslip->load(['items', 'employee']);
        });
    }
}
