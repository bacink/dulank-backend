<?php

namespace App\Models;

use Database\Factories\FlowNameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlowName extends Model
{
    /** @use HasFactory<FlowNameFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'flow_category_id',
        'name',
        'incentive',
        'unit',
        'flow_type',
        'assignees',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'incentive' => 'integer',
            'assignees' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FlowCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FlowCategory::class, 'flow_category_id');
    }
}
