<?php

namespace App\Models;

use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'flag', 'rtl', 'progress', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rtl' => 'boolean',
        ];
    }

    /**
     * @return HasMany<LanguageTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(LanguageTranslation::class);
    }
}
