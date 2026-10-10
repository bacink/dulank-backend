<?php

namespace App\Models;

use App\Enums\WishlistStatus;
use Database\Factories\WishlistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wishlist extends Model
{
    /** @use HasFactory<WishlistFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'name',
        'image',
        'price',
        'spec',
        'status',
        'added_at',
        'expire_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'status' => WishlistStatus::class,
            'added_at' => 'date',
            'expire_date' => 'date',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expire_date !== null && $this->expire_date->isPast();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
