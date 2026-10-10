<?php

namespace App\Models;

use Database\Factories\CalculatorComponentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalculatorComponent extends Model
{
    /** @use HasFactory<CalculatorComponentFactory> */
    use HasFactory;

    protected $fillable = ['kind', 'name', 'value', 'rate', 'minim', 'unit', 'used', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'rate' => 'integer',
            'minim' => 'integer',
        ];
    }
}
