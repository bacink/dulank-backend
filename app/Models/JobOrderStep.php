<?php

namespace App\Models;

use Database\Factories\JobOrderStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobOrderStep extends Model
{
    /** @use HasFactory<JobOrderStepFactory> */
    use HasFactory;

    protected $fillable = ['job_order_id', 'name', 'status', 'sort_order'];

    /**
     * @return BelongsTo<JobOrder, $this>
     */
    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }
}
