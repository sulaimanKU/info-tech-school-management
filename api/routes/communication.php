<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParentHomeController;
use App\Http\Controllers\PushDeviceController;
use App\Http\Controllers\TicketController;
use App\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(AuthenticateSession::class)->group(function () {
    Route::post('push-devices', [PushDeviceController::class, 'register'])->middleware('throttle:10,1');
    Route::delete('push-devices/{device}', [PushDeviceController::class, 'revoke'])->whereUuid('device');
    Route::get('parent/home', [ParentHomeController::class, 'index']);
    Route::get('tickets', [TicketController::class, 'index']);
    Route::post('tickets', [TicketController::class, 'store'])->middleware('throttle:20,1');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->whereUuid('ticket');
    Route::post('tickets/{ticket}/messages', [TicketController::class, 'reply'])->whereUuid('ticket')->middleware('throttle:30,1');
    Route::post('tickets/{ticket}/workflow', [TicketController::class, 'workflow'])->whereUuid('ticket');
    Route::get('communication/parents', [TicketController::class, 'parents']);
    Route::get('communication/staff', [TicketController::class, 'staff']);
    Route::get('announcements', [AnnouncementController::class, 'index']);
    Route::post('announcements', [AnnouncementController::class, 'store']);
    Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])->whereUuid('announcement');
    Route::put('announcements/{announcement}', [AnnouncementController::class, 'store'])->whereUuid('announcement');
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification');
});
