<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require __DIR__.'/../vendor/autoload.php';

    $app = require_once __DIR__.'/../bootstrap/app.php';

    echo 'LARAVEL BOOT OK';
} catch (Throwable $e) {
    echo '<pre>';
    echo 'ERROR: ' . $e->getMessage() . "\n\n";
    echo $e->getTraceAsString();
    echo '</pre>';
}