
<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$basePath = dirname(__DIR__);

$cachePath = '/tmp/laravel-bootstrap-cache';

if (!is_dir($cachePath)) {
    mkdir($cachePath, 0775, true);
}

if (!is_dir('/tmp/laravel-views')) {
    mkdir('/tmp/laravel-views', 0775, true);
}

putenv('APP_CONFIG_CACHE=' . $cachePath . '/config.php');
putenv('APP_EVENTS_CACHE=' . $cachePath . '/events.php');
putenv('APP_PACKAGES_CACHE=' . $cachePath . '/packages.php');
putenv('APP_ROUTES_CACHE=' . $cachePath . '/routes.php');
putenv('APP_SERVICES_CACHE=' . $cachePath . '/services.php');
putenv('VIEW_COMPILED_PATH=/tmp/laravel-views');

require $basePath . '/vendor/autoload.php';

$app = require $basePath . '/bootstrap/app.php';

$request = Request::capture();

$kernel = $app->make(Kernel::class);

$response = $kernel->handle($request);

$response->send();

$kernel->terminate($request, $response);
