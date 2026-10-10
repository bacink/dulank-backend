<?php

namespace App\Models;

use App\Enums\MachineKind;
use App\Enums\PaperOwnerType;
use Database\Factories\MachineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Machine extends Model
{
    /** @use HasFactory<MachineFactory> */
    use HasFactory;

    protected $fillable = [
        'owner_type',
        'partner_id',
        'kind',
        'source',
        'name',
        'type',
        'colors',
        'size',
        'max_area',
        'plate_cost',
        'minim',
        'druck',
        'price',
        'unit_price',
        'rate_cm',
        'putus_rate',
        'putus_minim',
        'kiss_rate',
        'kiss_minim',
        'published',
        'active',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_type' => PaperOwnerType::class,
            'kind' => MachineKind::class,
            'plate_cost' => 'integer',
            'minim' => 'integer',
            'druck' => 'integer',
            'price' => 'integer',
            'unit_price' => 'integer',
            'rate_cm' => 'integer',
            'putus_rate' => 'integer',
            'putus_minim' => 'integer',
            'kiss_rate' => 'integer',
            'kiss_minim' => 'integer',
            'published' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
