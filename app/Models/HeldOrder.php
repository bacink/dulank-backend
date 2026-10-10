<?php

namespace App\Models;

use Database\Factories\HeldOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeldOrder extends Model
{
    /** @use HasFactory<HeldOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer',
        'items',
        'sub_total',
        'discount',
        'delivery_fee',
        'tax',
        'total',
        'method',
        'delivery',
        'note',
        'held_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'items' => 'array',
            'sub_total' => 'integer',
            'discount' => 'integer',
            'delivery_fee' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
            'held_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
