
<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

$step = 'start';

try {
    $step = 'composer_autoload';
    require __DIR__ . '/../vendor/autoload.php';

    $step = 'bootstrap_laravel';
    $app = require __DIR__ . '/../bootstrap/app.php';

    $step = 'laravel_container';
    $app->make(
        Illuminate\Contracts\Console\Kernel::class
    )->bootstrap();

    $step = 'completed';

    echo json_encode([
        'status' => 'success',
        'message' => 'Laravel bootstrap berhasil',
        'step' => $step
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'failed_step' => $step,
        'error_type' => get_class($e)
    ]);
}
