<?php

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'discount',
        'limit',
        'used',
        'start_date',
        'end_date',
        'all_products',
        'once_per_customer',
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
            'start_date' => 'date',
            'end_date' => 'date',
            'all_products' => 'boolean',
            'once_per_customer' => 'boolean',
        ];
    }
}
