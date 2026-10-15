<?php

use Illuminate\Contracts\Http\Kernel;

header('Content-Type: application/json');
ini_set('display_errors', '0');

$step = 'start';

try {
    $step = 'autoload';
    require __DIR__ . '/../vendor/autoload.php';

    $step = 'application';
    $app = require __DIR__ . '/../bootstrap/app.php';

    $step = 'http_kernel';
    $kernel = $app->make(Kernel::class);

    $step = 'kernel_bootstrap';
    $kernel->bootstrap();

    echo json_encode([
        'status' => 'success',
        'step' => 'completed'
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'failed_step' => $step,
        'error_type' => get_class($e),
        'error_message' => $e->getMessage()
    ]);
}
