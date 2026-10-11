<?php

use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

// Backend REST API: root membalas JSON status, bukan halaman web.
Route::get('/', fn () => ApiResponse::success([
    'name' => config('app.name'),
    'status' => 'ok',
]));
