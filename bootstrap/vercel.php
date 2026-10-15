<?php

/**
 * Vercel PHP entry-point configuration.
 *
 * The deployed application is read-only. Only /tmp is writable. All runtime
 * caches, compiled views and temporary storage must point there before
 * Laravel initializes. Files saved here are EPHEMERAL, not persistent uploads.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/dev/stderr');

$runtime = '/tmp/dulank-laravel';
$storage = $runtime . '/storage';
$bootstrapCache = $runtime . '/bootstrap/cache';

foreach ([
    $bootstrapCache,
    $storage . '/app/private',
    $storage . '/app/public',
    $storage . '/framework/cache/data',
    $storage . '/framework/cache/locks',
    $storage . '/framework/sessions',
    $storage . '/framework/views',
    $storage . '/logs',
] as $directory) {
    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        throw new RuntimeException('Cannot initialize Laravel runtime directory.');
    }
}

$environment = [
    'LARAVEL_STORAGE_PATH' => $storage,
    'APP_CONFIG_CACHE' => $bootstrapCache . '/config.php',
    'APP_EVENTS_CACHE' => $bootstrapCache . '/events.php',
    'APP_PACKAGES_CACHE' => $bootstrapCache . '/packages.php',
    'APP_ROUTES_CACHE' => $bootstrapCache . '/routes.php',
    'APP_SERVICES_CACHE' => $bootstrapCache . '/services.php',
    'VIEW_COMPILED_PATH' => $storage . '/framework/views',
    'LOG_CHANNEL' => 'stderr',
];

foreach ($environment as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

// vercel-php executes api/index.php as its router. Tell Symfony/Laravel that
// the real front controller lives at /index.php; otherwise /api/health can
// be interpreted relative to an incorrect /api script base.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__) . '/public/index.php';
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__) . '/public';
