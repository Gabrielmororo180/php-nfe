<?php

use App\Infrastructure\Primary\Http\Controllers\NFeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes for NFe management API (Issuance and Cancellation).
|
*/

Route::prefix('nfe')->group(function () {
    Route::post('/emitir', [NFeController::class, 'emitir']);
    Route::post('/cancelar', [NFeController::class, 'cancelar']);
});
