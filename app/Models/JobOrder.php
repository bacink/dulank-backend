<?php

namespace App\Models;

use Database\Factories\JobOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobOrder extends Model
{
    /** @use HasFactory<JobOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'no',
        'order_id',
        'customer_id',
        'branch_id',
        'product_id',
        'flow_name_id',
        'flow_type',
        'due_date',
        'customer_name',
        'product_name',
        'job_title',
        'qty',
        'priority',
        'status',
        'workflow_type',
        'workflow_category',
        'sales_no',
        'sales_date',
        'shipping',
        'order_summary',
        'assigned_to',
        'input_specs',
        'output_specs',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'sales_date' => 'date',
            'qty' => 'float',
            'input_specs' => 'array',
            'output_specs' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<FlowName, $this>
     */
    public function flowName(): BelongsTo
    {
        return $this->belongsTo(FlowName::class);
    }

    /**
     * @return HasMany<JobOrderStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(JobOrderStep::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<JobBranch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(JobBranch::class);
    }

    /**
     * @return HasMany<JobProgress, $this>
     */
    public function progressEntries(): HasMany
    {
        return $this->hasMany(JobProgress::class);
    }
}
