<?php

namespace App\Models;

use Database\Factories\EmployeeSalaryAllowanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryAllowance extends Model
{
    /** @use HasFactory<EmployeeSalaryAllowanceFactory> */
    use HasFactory;

    protected $fillable = ['employee_salary_id', 'name', 'amount'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<EmployeeSalary, $this>
     */
    public function salary(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalary::class, 'employee_salary_id');
    }
}
