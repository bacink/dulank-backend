<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
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
        ResetPassword::createUrlUsing(function ($user, string $token): string {
            $base = $user->isAdmin() ? config('dulank.admin_url') : config('dulank.store_url');

            return rtrim($base, '/').'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->email]);
        });

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
