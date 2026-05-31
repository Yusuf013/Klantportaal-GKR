<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require __DIR__.'/../vendor/autoload.php';

    $app = require_once __DIR__.'/../bootstrap/app.php';

    $request = Illuminate\Http\Request::create('/health', 'GET');

    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

    $response = $kernel->handle($request);

    echo '<pre>';
    echo 'STATUS: ' . $response->getStatusCode() . "\n\n";
    echo $response->getContent();
    echo '</pre>';

    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    echo '<pre>';
    echo 'ERROR: ' . $e->getMessage() . "\n\n";
    echo $e->getTraceAsString();
    echo '</pre>';
}