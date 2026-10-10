<?php

namespace App\Models;

use Database\Factories\ModerationHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationHistory extends Model
{
    /** @use HasFactory<ModerationHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'listing_id',
        'action',
        'duration',
        'notify',
        'message',
        'acted_by',
        'acted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notify' => 'boolean',
            'acted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
