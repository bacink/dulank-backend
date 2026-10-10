<?php

namespace App\Models;

use Database\Factories\IncentiveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incentive extends Model
{
    /** @use HasFactory<IncentiveFactory> */
    use HasFactory;

    protected $fillable = ['code', 'employee_id', 'period', 'qty_complete', 'total_amount', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty_complete' => 'float',
            'total_amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
