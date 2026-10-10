<?php

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'discount',
        'limit',
        'used',
        'valid_from',
        'valid_until',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'discount' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }
}
