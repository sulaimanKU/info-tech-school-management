<?php

use App\Http\Controllers\AcademicSettingsController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BehaviorController;
use App\Http\Controllers\CoordinatorController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\TeacherWorkspaceController;
use App\Http\Controllers\TeachingAssignmentController;
use App\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(AuthenticateSession::class)->group(function () {
    Route::get('my-teaching', [TeacherWorkspaceController::class, 'show']);
    Route::get('my-teaching/sections/{section}/students', [TeacherWorkspaceController::class, 'students'])->whereUuid('section');
    Route::get('academic-roster', [TeacherWorkspaceController::class, 'roster']);
    Route::get('teaching-assignments', [TeachingAssignmentController::class, 'index']);
    Route::get('teaching-assignments/teachers', [TeachingAssignmentController::class, 'teachers']);
    Route::get('academic-settings', [AcademicSettingsController::class, 'show']);
    Route::put('academic-settings', [AcademicSettingsController::class, 'save']);
    Route::get('attendance/register', [AttendanceController::class, 'register']);
    Route::post('attendance/register', [AttendanceController::class, 'save']);
    Route::get('attendance/{register}/revisions', [AttendanceController::class, 'revisions'])->whereUuid('register');
    Route::get('students/{student}/attendance', [AttendanceController::class, 'history'])->whereUuid('student');
    Route::post('school-days', [AttendanceController::class, 'calendar']);
    Route::get('diary', [DiaryController::class, 'index']);
    Route::post('diary', [DiaryController::class, 'save']);
    Route::get('diary/{diary}', [DiaryController::class, 'show'])->whereUuid('diary');
    Route::patch('diary/{diary}', [DiaryController::class, 'save'])->whereUuid('diary');
    Route::post('diary/{diary}/review', [DiaryController::class, 'review'])->whereUuid('diary');
    Route::get('diary/{diary}/revisions', [DiaryController::class, 'revisions'])->whereUuid('diary');
    Route::get('assessments', [AssessmentController::class, 'index']);
    Route::post('assessments', [AssessmentController::class, 'store']);
    Route::get('assessments/{assessment}', [AssessmentController::class, 'show'])->whereUuid('assessment');
    Route::put('assessments/{assessment}/marks', [AssessmentController::class, 'marks'])->whereUuid('assessment');
    Route::post('assessments/{assessment}/review', [AssessmentController::class, 'review'])->whereUuid('assessment');
    Route::get('students/{student}/assessments', [AssessmentController::class, 'student'])->whereUuid('student');
    Route::put('grading-rules', [AssessmentController::class, 'grading']);
    Route::get('students/{student}/behavior', [BehaviorController::class, 'index'])->whereUuid('student');
    Route::post('students/{student}/behavior', [BehaviorController::class, 'store'])->whereUuid('student');
    Route::post('behavior/{observation}/publish', [BehaviorController::class, 'publish'])->whereUuid('observation');
    Route::get('coordinator', [CoordinatorController::class, 'index']);
    Route::post('coordinator', [CoordinatorController::class, 'save']);
    Route::patch('coordinator/{record}', [CoordinatorController::class, 'save'])->whereUuid('record');
});
