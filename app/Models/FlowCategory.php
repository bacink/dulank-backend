<?php

namespace App\Models;

use Database\Factories\FlowCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlowCategory extends Model
{
    /** @use HasFactory<FlowCategoryFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'used_count', 'created_by'];

    /**
     * @return HasMany<FlowName, $this>
     */
    public function flowNames(): HasMany
    {
        return $this->hasMany(FlowName::class);
    }
}
