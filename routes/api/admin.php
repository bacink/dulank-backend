<?php

use App\Http\Controllers\Api\V1\Admin\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware(['throttle:api', 'throttle:auth']);

    Route::middleware(['auth:sanctum', 'abilities:admin', 'throttle:admin'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
    });
});
