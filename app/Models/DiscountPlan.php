<?php

namespace App\Models;

use Database\Factories\DiscountPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountPlan extends Model
{
    /** @use HasFactory<DiscountPlanFactory> */
    use HasFactory;

    protected $fillable = ['name', 'customers', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customers' => 'array',
        ];
    }

    /**
     * @return HasMany<Discount, $this>
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }
}
