<?php

namespace App\Models;

use Database\Factories\ProfitTierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfitTier extends Model
{
    /** @use HasFactory<ProfitTierFactory> */
    use HasFactory;

    protected $fillable = ['min_qty', 'max_qty', 'profit_pos_percent', 'profit_webstore_percent'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profit_pos_percent' => 'float',
            'profit_webstore_percent' => 'float',
        ];
    }
}
