
<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

header('Content-Type: application/json');
ini_set('display_errors', '0');

$step = 'start';

try {
    $step = 'prepare_cache';

    $cachePath = '/tmp/laravel-cache';

    if (!is_dir($cachePath)) {
        if (!mkdir($cachePath, 0775, true) && !is_dir($cachePath)) {
            throw new RuntimeException('Cannot create cache directory');
        }
    }

    foreach ([
        'APP_CONFIG_CACHE' => 'config.php',
        'APP_EVENTS_CACHE' => 'events.php',
        'APP_PACKAGES_CACHE' => 'packages.php',
        'APP_ROUTES_CACHE' => 'routes.php',
        'APP_SERVICES_CACHE' => 'services.php',
    ] as $key => $filename) {
        putenv($key . '=' . $cachePath . '/' . $filename);
        $_ENV[$key] = $cachePath . '/' . $filename;
        $_SERVER[$key] = $cachePath . '/' . $filename;
    }

    $step = 'autoload';
    require __DIR__ . '/../vendor/autoload.php';

    $step = 'application';
    $app = require __DIR__ . '/../bootstrap/app.php';

    $step = 'kernel';
    $kernel = $app->make(Kernel::class);

    $step = 'kernel_bootstrap';
    $kernel->bootstrap();

    echo json_encode([
        'status' => 'success',
        'step' => 'completed',
        'message' => 'Laravel bootstrap berhasil'
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'failed_step' => $step,
        'error_type' => get_class($e)
    ]);
}
