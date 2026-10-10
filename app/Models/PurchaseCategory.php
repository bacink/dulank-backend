<?php

namespace App\Models;

use Database\Factories\PurchaseCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseCategory extends Model
{
    /** @use HasFactory<PurchaseCategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'status'];

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
