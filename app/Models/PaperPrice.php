<?php

namespace App\Models;

use Database\Factories\PaperPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaperPrice extends Model
{
    /** @use HasFactory<PaperPriceFactory> */
    use HasFactory;

    protected $fillable = [
        'paper_group_id',
        'paper_type_id',
        'source',
        'name',
        'min_order',
        'multiple',
        'price',
        'published',
        'active',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'min_order' => 'float',
            'multiple' => 'float',
            'published' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PaperGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(PaperGroup::class, 'paper_group_id');
    }

    /**
     * @return BelongsTo<PaperType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(PaperType::class, 'paper_type_id');
    }
}
