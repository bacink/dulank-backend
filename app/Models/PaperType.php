<?php

namespace App\Models;

use Database\Factories\PaperTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaperType extends Model
{
    /** @use HasFactory<PaperTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'paper_group_id',
        'partner_id',
        'source',
        'name',
        'merk',
        'gsm',
        'dimension',
        'unit',
        'stock',
        'unit_stock',
        'price',
        'min_order',
        'multiple',
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
            'gsm' => 'float',
            'stock' => 'float',
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
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
