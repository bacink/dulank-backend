<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;

/**
 * Penomoran dokumen (PRD §7.2).
 *
 * Format default: {PREFIX}-{urut 3 digit} (mis. TK-004). Prefix diambil dari
 * config/dulank.php, dapat di-override oleh setting grup `prefixes`.
 */
final class DocumentNumber
{
    /**
     * @param  Builder<*>|null  $query  Query model dokumen untuk cek duplikat.
     */
    public static function next(string $type, ?Builder $query = null, string $column = 'number', int $pad = 3): string
    {
        $prefix = self::prefix($type);

        if ($query === null) {
            return sprintf('%s-%s', $prefix, str_pad((string) now()->timestamp, 13, '0', STR_PAD_LEFT));
        }

        $sequence = (int) $query->clone()->where($column, 'like', $prefix.'-%')->count() + 1;

        do {
            $candidate = sprintf('%s-%s', $prefix, str_pad((string) $sequence, $pad, '0', STR_PAD_LEFT));
            $exists = $query->clone()->where($column, $candidate)->exists();
            $sequence++;
        } while ($exists);

        return $candidate;
    }

    public static function prefix(string $type): string
    {
        $setting = Setting::query()
            ->where('group', 'prefixes')
            ->where('key', $type)
            ->first();

        if ($setting !== null) {
            $value = $setting->value;

            if (is_array($value) && isset($value['prefix']) && is_string($value['prefix'])) {
                return $value['prefix'];
            }

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return (string) config('dulank.documents.'.$type, strtoupper($type));
    }
}
