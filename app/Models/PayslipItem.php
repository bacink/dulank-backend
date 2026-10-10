<?php

namespace App\Models;

use Database\Factories\PayslipItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipItem extends Model
{
    /** @use HasFactory<PayslipItemFactory> */
    use HasFactory;

    protected $fillable = ['payslip_id', 'label', 'qty', 'rate', 'amount'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'rate' => 'integer',
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Payslip, $this>
     */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}
