<?php

namespace App\Models;

use Database\Factories\LegacyIdMapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Pemetaan ID data mock lama (store/admin) ke model baru.
 */
class LegacyIdMap extends Model
{
    /** @use HasFactory<LegacyIdMapFactory> */
    use HasFactory;

    protected $table = 'legacy_id_map';

    protected $fillable = ['source', 'entity', 'legacy_id', 'model_type', 'model_id'];

    /**
     * @return MorphTo<Model, $this>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
