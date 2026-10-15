<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    require __DIR__.'/api/v1.php';
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Laravel API berjalan'
    ]);
});