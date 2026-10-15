<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

ini_set('display_errors', '0');

$step = 'prepare';

try {
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
        $value = $cachePath . '/' . $filename;

        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    require __DIR__ . '/../vendor/autoload.php';

    $app = require __DIR__ . '/../bootstrap/app.php';

    $kernel = $app->make(Kernel::class);

    $step = 'handle_request';

    $request = Request::capture();
    $response = $kernel->handle($request);

    $step = 'send_response';

    $response->send();
    $kernel->terminate($request, $response);

} catch (Throwable $e) {
    error_log('[Laravel Error] ' . (string) $e);

    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);

        echo json_encode([
            'status' => 'error',
            'failed_step' => $step,
            'error_type' => get_class($e)
        ]);
    }
}
