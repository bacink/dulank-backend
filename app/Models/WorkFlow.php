<?php

namespace App\Models;

use Database\Factories\WorkFlowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkFlow extends Model
{
    /** @use HasFactory<WorkFlowFactory> */
    use HasFactory;

    protected $fillable = ['no', 'date', 'flow_category_id', 'product_id', 'workflow_steps', 'steps'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'steps' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FlowCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FlowCategory::class, 'flow_category_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relasi step (kolom `steps` JSON dipakai untuk payload ringkas).
     *
     * @return HasMany<WorkFlowStep, $this>
     */
    public function stepList(): HasMany
    {
        return $this->hasMany(WorkFlowStep::class)->orderBy('sort_order');
    }
}
