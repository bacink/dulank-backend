<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/dev/stderr');

$step = 'prepare';

try {
    // Menyiapkan direktori sementara di Vercel.
    $cachePath = '/tmp/laravel-cache';
    $viewPath = '/tmp/laravel-views';

    foreach ([$cachePath, $viewPath] as $directory) {
        if (! is_dir($directory)) {
            if (
                ! mkdir($directory, 0775, true)
                && ! is_dir($directory)
            ) {
                throw new RuntimeException(
                    'Cannot create directory: ' . $directory
                );
            }
        }
    }

    // Mengarahkan cache Laravel ke direktori writable.
    $environment = [
        'APP_CONFIG_CACHE' => $cachePath . '/config.php',
        'APP_EVENTS_CACHE' => $cachePath . '/events.php',
        'APP_PACKAGES_CACHE' => $cachePath . '/packages.php',
        'APP_ROUTES_CACHE' => $cachePath . '/routes.php',
        'APP_SERVICES_CACHE' => $cachePath . '/services.php',
        'VIEW_COMPILED_PATH' => $viewPath,
    ];

    foreach ($environment as $key => $value) {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    // Memuat Composer.
    $step = 'autoload';

    require __DIR__ . '/../vendor/autoload.php';

    // Membuat aplikasi Laravel.
    $step = 'application';

    $app = require __DIR__ . '/../bootstrap/app.php';

    // Membuat HTTP Kernel.
    $step = 'http_kernel';

    $kernel = $app->make(Kernel::class);

    // Menangani request HTTP.
    $step = 'handle_request';

    $request = Request::capture();
    $response = $kernel->handle($request);

    // Diagnostik status respons.
    error_log(
        '[HTTP RESPONSE] '
        . $request->method()
        . ' '
        . $request->path()
        . ' | Status: '
        . $response->getStatusCode()
    );

    // Mengirim respons ke client.
    $step = 'send_response';

    $response->send();

    // Mengakhiri lifecycle Laravel.
    $step = 'terminate';

    $kernel->terminate($request, $response);

} catch (\Throwable $e) {
    error_log(
        '[VERCEL LARAVEL ERROR] Step: '
        . $step
        . ' | '
        . (string) $e
    );

    if (! headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');

        echo json_encode([
            'status' => 'error',
            'message' => 'Internal server error',
            'failed_step' => $step,
        ]);
    }
}
