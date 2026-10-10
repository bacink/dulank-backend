<?php

namespace App\Models;

use Database\Factories\JobBranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobBranch extends Model
{
    /** @use HasFactory<JobBranchFactory> */
    use HasFactory;

    protected $fillable = [
        'no',
        'job_order_id',
        'order_no',
        'customer_id',
        'branch_id',
        'branch',
        'customer',
        'product',
        'flow_name',
        'priority',
        'status',
        'job_title',
        'description',
        'qty',
        'date',
        'date_finish',
        'info_list',
        'after_info_list',
        'assignees',
        'incentive_amount',
        'incentive_unit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'date' => 'date',
            'date_finish' => 'date',
            'info_list' => 'array',
            'after_info_list' => 'array',
            'assignees' => 'array',
            'incentive_amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<JobOrder, $this>
     */
    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    /**
     * @return HasMany<JobBranchHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(JobBranchHistory::class);
    }

    /**
     * @return HasMany<MyIncentive, $this>
     */
    public function incentives(): HasMany
    {
        return $this->hasMany(MyIncentive::class, 'job_branch_id');
    }
}
