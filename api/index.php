<?php

header('Content-Type: application/json');
ini_set('display_errors', '0');

$step = 'start';

try {
    $step = 'autoload';
    require __DIR__ . '/../vendor/autoload.php';

    $step = 'bootstrap';
    $app = require __DIR__ . '/../bootstrap/app.php';

    echo json_encode([
        'status' => 'success',
        'message' => 'Laravel application created',
        'step' => $step
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'step' => $step,
        'type' => get_class($e)
    ]);
}
