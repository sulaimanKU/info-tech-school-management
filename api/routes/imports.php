<?php

use App\Http\Controllers\ImportController;
use App\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware([AuthenticateSession::class, 'throttle:30,1'])->group(function () {
    Route::get('imports', [ImportController::class, 'index']);
    Route::post('imports', [ImportController::class, 'upload']);
    Route::get('imports/{batch}', [ImportController::class, 'show'])->whereUuid('batch');
    Route::post('imports/{batch}/preview', [ImportController::class, 'preview'])->whereUuid('batch');
    Route::post('imports/{batch}/confirm', [ImportController::class, 'confirm'])->whereUuid('batch');
    Route::delete('imports/{batch}', [ImportController::class, 'destroy'])->whereUuid('batch');
    Route::get('biometric/mappings', [ImportController::class, 'mappings']);
    Route::delete('biometric/mappings/{mapping}', [ImportController::class, 'revokeMapping'])->whereUuid('mapping');
    Route::post('biometric/mappings', [ImportController::class, 'mapping']);
    Route::get('biometric/summary', [ImportController::class, 'biometric']);
});
