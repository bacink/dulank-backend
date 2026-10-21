<?php

namespace App\Http\Middleware;

use App\Models\UserAdmin;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->isAdmin() || $user->status !== 'Active'
            || UserAdmin::query()->where('user_id', $user->id)->where('status', '!=', 'Active')->exists()) {
            return ApiResponse::error('Akun admin tidak aktif atau tidak memiliki akses.', 403);
        }

        return $next($request);
    }
}
