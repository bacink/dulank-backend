<?php

namespace App\Models;

use Database\Factories\MyIncentiveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MyIncentive extends Model
{
    /** @use HasFactory<MyIncentiveFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'employee_name',
        'job_order_id',
        'job_branch_id',
        'branch_id',
        'date',
        'job_title',
        'flow_name',
        'process',
        'incentive',
        'unit',
        'qty',
        'amount',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'incentive' => 'integer',
            'qty' => 'float',
            'amount' => 'integer',
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
     * @return BelongsTo<JobOrder, $this>
     */
    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    /**
     * @return BelongsTo<JobBranch, $this>
     */
    public function jobBranch(): BelongsTo
    {
        return $this->belongsTo(JobBranch::class);
    }
}
