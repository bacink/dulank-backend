<?php

namespace App\Models;

use Database\Factories\EmployeeSalaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeSalary extends Model
{
    /** @use HasFactory<EmployeeSalaryFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'name',
        'salary',
        'system',
        'allowance_total',
        'overtime_rate',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salary' => 'integer',
            'allowance_total' => 'integer',
            'overtime_rate' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<EmployeeSalaryAllowance, $this>
     */
    public function allowances(): HasMany
    {
        return $this->hasMany(EmployeeSalaryAllowance::class);
    }
}
