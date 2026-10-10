<?php

namespace App\Models;

use Database\Factories\IncomeCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomeCategory extends Model
{
    /** @use HasFactory<IncomeCategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'status'];

    /**
     * @return HasMany<Income, $this>
     */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }
}
