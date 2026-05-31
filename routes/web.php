<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return 'HOME OK';
});

Route::get('/health', function () {
    return 'OK';
});

require __DIR__.'/auth.php';