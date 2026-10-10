<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    require __DIR__.'/api/v1.php';
});

require __DIR__.'/api/compatibility.php';
