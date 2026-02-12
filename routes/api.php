<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AuthApiKeyMiddleware;

Route::middleware([AuthApiKeyMiddleware::class])->group(function () {
    Route::get('/', function () {
        return 'welcome';
    });
});

