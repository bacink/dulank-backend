<?php

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\DiscountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Discount extends Model
{
    /** @use HasFactory<DiscountFactory> */
    use HasFactory;

    protected $fillable = [
        'discount_plan_id',
        'name',
        'value',
        'type',
        'valid_from',
        'valid_till',
        'days',
        'products',
        'used',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'integer',
            'valid_from' => 'date',
            'valid_till' => 'date',
            'days' => 'array',
            'products' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DiscountPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(DiscountPlan::class, 'discount_plan_id');
    }
}
