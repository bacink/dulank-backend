<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employee_code',
        'name',
        'department_id',
        'designation_id',
        'address',
        'detail_address',
        'phone',
        'join_date',
        'status',
        'gender',
        'dob',
        'join_channel',
        'emergency_contact_name',
        'emergency_contact_phone',
        'email',
        'avatar_path',
        'photo_id_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'dob' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * @return HasOne<EmployeeSalary, $this>
     */
    public function salary(): HasOne
    {
        return $this->hasOne(EmployeeSalary::class);
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * @return HasMany<Incentive, $this>
     */
    public function incentives(): HasMany
    {
        return $this->hasMany(Incentive::class);
    }

    /**
     * @return HasMany<MyIncentive, $this>
     */
    public function myIncentives(): HasMany
    {
        return $this->hasMany(MyIncentive::class);
    }
}
