<?php

namespace App\Models;

use App\Enums\CartItemStatus;
use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'name',
        'image',
        'quantity',
        'price',
        'spec',
        'status',
        'job_title',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'price' => 'integer',
            'status' => CartItemStatus::class,
        ];
    }

    /**
     * @param  Builder<CartItem>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', CartItemStatus::Active);
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
