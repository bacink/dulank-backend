<?php

namespace App\Models;

use Database\Factories\PaperSizeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaperSize extends Model
{
    /** @use HasFactory<PaperSizeFactory> */
    use HasFactory;

    protected $fillable = [
        'paper_group_id',
        'source',
        'name',
        'dimension',
        'length',
        'width',
        'unit',
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
            'length' => 'float',
            'width' => 'float',
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
}
