<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuardianController;
use App\Http\Controllers\ParentAuthController;
use App\Http\Controllers\ParentChildController;
use App\Http\Controllers\PrivateFileController;
use App\Http\Controllers\RbacController;
use App\Http\Controllers\StaffDirectoryController;
use App\Http\Controllers\StudentController;
use App\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('parent-auth/request', [ParentAuthController::class, 'requestOtp'])->middleware('throttle:parent-request');
    Route::post('parent-auth/verify', [ParentAuthController::class, 'verify'])->middleware('throttle:parent-verify');
    Route::middleware(AuthenticateSession::class)->group(function () {
        Route::get('dashboard-summary', [DashboardController::class, 'show']);
        Route::get('academic-filter-options', [CatalogController::class, 'filterOptions']);
        Route::get('catalog/{catalog}', [CatalogController::class, 'index']);
        Route::post('catalog/{catalog}', [CatalogController::class, 'store']);
        Route::patch('catalog/{catalog}/{id}', [CatalogController::class, 'update'])->whereUuid('id');
        Route::post('catalog/{catalog}/{id}/deactivate', [CatalogController::class, 'deactivate'])->whereUuid('id');
        Route::get('auth/history', [StaffDirectoryController::class, 'history']);
        Route::patch('staff-accounts/{user}/status', [StaffDirectoryController::class, 'status'])->whereUuid('user');
        Route::get('staff-accounts/{user}', [StaffDirectoryController::class, 'show'])->whereUuid('user');
        Route::patch('staff-accounts/{user}', [StaffDirectoryController::class, 'update'])->whereUuid('user');
        Route::get('rbac', [RbacController::class, 'index']);
        Route::put('rbac/{role}', [RbacController::class, 'update']);
        Route::get('staff-accounts', [StaffDirectoryController::class, 'index']);
        Route::post('staff-accounts', [CatalogController::class, 'createStaff']);
        Route::delete('students/{student}/photo', [PrivateFileController::class, 'removeStudentPhoto'])->whereUuid('student');
        Route::delete('guardians/{guardian}/photo', [PrivateFileController::class, 'removeGuardianPhoto'])->whereUuid('guardian');
        Route::get('parent/children/{student}/overview', [ParentChildController::class, 'overview'])->whereUuid('student');
        Route::post('files', [PrivateFileController::class, 'store']);
        Route::get('files/{file}', [PrivateFileController::class, 'show'])->whereUuid('file');

        Route::get('students', [StudentController::class, 'index']);
        Route::post('students', [StudentController::class, 'store']);
        Route::get('students/{student}', [StudentController::class, 'show'])->whereUuid('student');
        Route::patch('students/{student}', [StudentController::class, 'update'])->whereUuid('student');
        Route::post('students/{student}/transitions', [StudentController::class, 'transition'])->whereUuid('student');
        Route::get('guardians/{guardian}', [GuardianController::class, 'show'])->whereUuid('guardian');
        Route::patch('guardians/{guardian}', [GuardianController::class, 'update'])->whereUuid('guardian');
        Route::delete('guardians/{guardian}/links/{student}', [GuardianController::class, 'unlink'])->whereUuid('guardian')->whereUuid('student');
        Route::get('guardians', [GuardianController::class, 'index']);
        Route::post('guardians', [GuardianController::class, 'store']);
        Route::put('guardians/{guardian}/links', [GuardianController::class, 'link'])->whereUuid('guardian');
    });
});
