<?php

namespace App\Http\Middleware;

use App\Models\BanIp;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blokir request dari alamat IP yang masuk daftar ban (PRD setting `ban_ip`).
 */
class BlockBannedIp
{
    public function handle(Request $request, Closure $next): Response
    {
        // Liveness checks must not depend on the ban-list database query.
        if ($request->is('api/health')) {
            return $next($request);
        }

        /** @var array<int, string> $banned */
        $banned = Cache::remember(
            'ban_ips',
            now()->addMinutes(5),
            fn (): array => BanIp::query()->where('status', 'Active')->pluck('ip_address')->all(),
        );

        if ($banned !== [] && in_array($request->ip(), $banned, true)) {
            return ApiResponse::error('Akses dari alamat IP ini diblokir.', 403);
        }

        return $next($request);
    }
}
