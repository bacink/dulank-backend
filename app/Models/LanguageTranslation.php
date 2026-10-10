<?php

namespace App\Models;

use Database\Factories\LanguageTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LanguageTranslation extends Model
{
    /** @use HasFactory<LanguageTranslationFactory> */
    use HasFactory;

    protected $fillable = ['language_id', 'key', 'value'];

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
