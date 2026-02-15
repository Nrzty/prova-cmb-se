<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AuthApiKeyMiddleware;
use App\Http\Controllers\Integration\OccurrenceIntegrationController;

Route::middleware([AuthApiKeyMiddleware::class])->group(function () {
    Route::get('/', function () {
        return 'welcome';
    });

    Route::post('/integrations/occurrences', [OccurrenceIntegrationController::class, 'store']);
});

