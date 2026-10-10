<?php

namespace App\Models;

use Database\Factories\JobProgressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobProgress extends Model
{
    /** @use HasFactory<JobProgressFactory> */
    use HasFactory;

    protected $fillable = [
        'job_order_id',
        'progress_code',
        'product',
        'description',
        'process',
        'completed_by',
        'time',
        'note',
        'is_completed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time' => 'datetime',
            'is_completed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<JobOrder, $this>
     */
    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }
}
