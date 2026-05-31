<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

echo '<pre>';

echo 'Laravel booted: YES' . PHP_EOL;

echo 'storage/framework/down exists: ';
var_dump(file_exists(__DIR__.'/../storage/framework/down'));

echo PHP_EOL;

if (method_exists($app, 'isDownForMaintenance')) {
    echo 'app isDownForMaintenance: ';
    var_dump($app->isDownForMaintenance());
}

echo PHP_EOL;

echo 'APP_MAINTENANCE_DRIVER: ' . ($_ENV['APP_MAINTENANCE_DRIVER'] ?? getenv('APP_MAINTENANCE_DRIVER') ?: 'not set') . PHP_EOL;
echo 'APP_MAINTENANCE_STORE: ' . ($_ENV['APP_MAINTENANCE_STORE'] ?? getenv('APP_MAINTENANCE_STORE') ?: 'not set') . PHP_EOL;

echo '</pre>';