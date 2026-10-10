<?php

namespace App\Models;

use Database\Factories\WorkFlowStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkFlowStep extends Model
{
    /** @use HasFactory<WorkFlowStepFactory> */
    use HasFactory;

    protected $fillable = ['work_flow_id', 'name', 'category', 'template', 'sort_order'];

    /**
     * @return BelongsTo<WorkFlow, $this>
     */
    public function workFlow(): BelongsTo
    {
        return $this->belongsTo(WorkFlow::class);
    }
}
