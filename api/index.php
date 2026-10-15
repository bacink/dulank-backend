<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');

$checks = [
    'php_version' => PHP_VERSION,
    'vendor_autoload' => file_exists(__DIR__ . '/../vendor/autoload.php'),
    'bootstrap_app' => file_exists(__DIR__ . '/../bootstrap/app.php'),
    'storage_directory' => is_dir(__DIR__ . '/../storage'),
    'bootstrap_cache_directory' => is_dir(__DIR__ . '/../bootstrap/cache'),
    'app_key_exists' => !empty(getenv('APP_KEY')),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
];

echo json_encode([
    'status' => 'diagnostic',
    'checks' => $checks
], JSON_PRETTY_PRINT);