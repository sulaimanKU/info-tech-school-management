<?php

use App\Http\Controllers\ExamScheduleController;
use App\Http\Controllers\ReportController;
use App\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(AuthenticateSession::class)->group(function () {
    Route::get('reports/{type}', [ReportController::class, 'show'])->middleware('throttle:10,1');
    Route::get('exam-schedules', [ExamScheduleController::class, 'index']);
    Route::post('exam-schedules', [ExamScheduleController::class, 'save']);
    Route::put('exam-schedules/{exam}', [ExamScheduleController::class, 'save'])->whereUuid('exam');
});
