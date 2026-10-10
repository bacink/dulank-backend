<?php

namespace App\Models;

use Database\Factories\PartnerMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerMetric extends Model
{
    /** @use HasFactory<PartnerMetricFactory> */
    use HasFactory;

    protected $fillable = [
        'partner_id',
        'paper_count',
        'printing_count',
        'lamination_count',
        'die_cutting_count',
        'foil_count',
        'group_count',
        'type_count',
    ];

    /**
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
