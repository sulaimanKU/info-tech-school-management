<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\AuthenticateSession;
use App\Http\Middleware\RequirePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::middleware(AuthenticateSession::class)->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('school-settings', function (Request $r) {
            return response()->json(['data' => DB::table('school_settings')->get()->mapWithKeys(fn ($s) => [$s->key => json_decode($s->value, true)]), 'error' => null]);
        })->middleware(RequirePermission::class.':settings.read');
    });
});

require __DIR__.'/mobile.php';

require __DIR__.'/academics.php';

require __DIR__.'/finance.php';

require __DIR__.'/communication.php';

require __DIR__.'/imports.php';

require __DIR__.'/reports.php';
