<?php

namespace App\Models;

use Database\Factories\PayslipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Payslip extends Model
{
    /** @use HasFactory<PayslipFactory> */
    use HasFactory;

    protected $fillable = [
        'slip_no',
        'employee_id',
        'period',
        'salary_rate',
        'day_worked',
        'allowance',
        'overtime',
        'deduction',
        'total',
        'status',
        'paid_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salary_rate' => 'integer',
            'day_worked' => 'float',
            'allowance' => 'integer',
            'overtime' => 'integer',
            'deduction' => 'integer',
            'total' => 'integer',
            'paid_date' => 'date',
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
     * @return HasMany<PayslipItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayslipItem::class);
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
