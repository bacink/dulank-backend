<?php

namespace App\Services;

use App\Models\UserAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class BranchAccess
{
    /** Null means explicitly global access; [] means no assigned branch. */
    public static function ids(): ?array
    {
        $user = Auth::user();
        if (! $user || ! $user->isAdmin() || $user->hasRole('super-admin')) {
            return null;
        }
        $profile = UserAdmin::query()->where('user_id', $user->id)->first();
        if ($profile?->all_stores) {
            return null;
        }
        if ($profile === null) {
            return [];
        }

        return array_values(array_map('intval', $profile->stores ?? []));
    }

    public static function scope(Builder $query, string $column): void
    {
        if (($ids = self::ids()) !== null) {
            $query->whereIn($query->getModel()->qualifyColumn($column), $ids);
        }
    }

    public static function assertAllowed(mixed $id): void
    {
        $ids = self::ids();
        abort_if($ids !== null && ! in_array((int) $id, $ids, true), 403, 'Cabang tidak termasuk akses akun ini.');
    }
}
