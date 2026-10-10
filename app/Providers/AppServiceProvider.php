<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (?User $user, string $ability, array $arguments = []): ?bool {
            if ($user === null || ! $user->isAdmin()) {
                return null;
            }

            // Bypass policy hanya untuk pemeriksaan berbasis model/kelas
            // (ownership). Permission modul (akses tanpa argumen) tetap
            // dievaluasi oleh spatie/laravel-permission.
            $subject = $arguments[0] ?? null;

            if ($subject instanceof Model || is_string($subject)) {
                return true;
            }

            return null;
        });

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)
            ->by($request->ip()));

        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
    }
}
