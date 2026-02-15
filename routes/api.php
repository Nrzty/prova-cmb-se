<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AuthApiKeyMiddleware;
use App\Http\Controllers\Integration\OccurrenceIntegrationController;
use App\Http\Controllers\Api\OccurrenceController;
use App\Http\Controllers\Api\OccurrenceDispatchController;


Route::middleware([AuthApiKeyMiddleware::class])->group(function () {
    Route::get('/', function () {
        return 'welcome';
    });

    // API INTERNA
    Route::get('/occurrences', [OccurrenceController::class, 'listAllOccurences']);
    Route::post('/occurrences/{id}/start', [OccurrenceController::class, 'start']);
    Route::post('/occurrences/{id}/resolve', [OccurrenceController::class, 'resolve']);
    Route::post('/occurrences/{id}/dispatches', [OccurrenceDispatchController::class, 'dispatches']);
    Route::post('/dispatches/{id}/close', [OccurrenceDispatchController::class, 'close']);

    // INTEGRAÇÃO EXTERNA
    Route::post('/integrations/occurrences', [OccurrenceIntegrationController::class, 'store']);
});
