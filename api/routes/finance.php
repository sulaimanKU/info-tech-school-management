<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FeeConfigurationController;
use App\Http\Controllers\FeeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayrollController;
use App\Http\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(AuthenticateSession::class)->group(function () {
    Route::get('finance/configuration', [FeeConfigurationController::class, 'show']);
    Route::post('finance/fee-schedules', [FeeConfigurationController::class, 'schedule']);
    Route::post('finance/fee-assignments', [FeeConfigurationController::class, 'assignment']);
    Route::post('finance/discount-policies', [FeeConfigurationController::class, 'policy']);
    Route::post('finance/payment-methods', [FeeConfigurationController::class, 'method']);
    Route::get('finance/discounts', [FeeConfigurationController::class, 'discounts']);
    Route::post('finance/discounts', [FeeConfigurationController::class, 'requestDiscount']);
    Route::post('finance/discounts/{discount}/approve', [FeeConfigurationController::class, 'approveDiscount'])->whereUuid('discount');
    Route::post('finance/discounts/{discount}/revoke', [FeeConfigurationController::class, 'revokeDiscount'])->whereUuid('discount');
    Route::get('finance/invoices', [FeeController::class, 'index']);
    Route::post('finance/invoices', [FeeController::class, 'store']);
    Route::get('finance/invoices/{invoice}', [FeeController::class, 'show'])->whereUuid('invoice');
    Route::post('finance/invoices/{invoice}/adjust', [FeeController::class, 'adjust'])->whereUuid('invoice');
    Route::get('students/{student}/finance', [FeeController::class, 'student'])->whereUuid('student');
    Route::get('finance/payments', [PaymentController::class, 'index']);
    Route::get('finance/payments/{payment}', [PaymentController::class, 'show'])->whereUuid('payment');
    Route::post('finance/payments', [PaymentController::class, 'store']);
    Route::post('finance/payments/{payment}/verify', [PaymentController::class, 'verify'])->whereUuid('payment');
    Route::post('finance/payments/{payment}/cancel', [PaymentController::class, 'cancel'])->whereUuid('payment');
    Route::post('finance/payments/{payment}/refund', [PaymentController::class, 'refund'])->whereUuid('payment');
    Route::get('finance/expenses', [ExpenseController::class, 'index']);
    Route::post('finance/expenses', [ExpenseController::class, 'store']);
    Route::post('finance/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->whereUuid('expense');
    Route::post('finance/expenses/{expense}/deny', [ExpenseController::class, 'deny'])->whereUuid('expense');
    Route::post('finance/expenses/{expense}/pay', [ExpenseController::class, 'pay'])->whereUuid('expense');
    Route::get('finance/payroll', [PayrollController::class, 'index']);
    Route::get('finance/payroll/{payroll}', [PayrollController::class, 'show'])->whereUuid('payroll');
    Route::post('finance/payroll', [PayrollController::class, 'store']);
    Route::post('finance/compensation', [PayrollController::class, 'compensation']);
    Route::post('finance/payroll-adjustments', [PayrollController::class, 'adjustment']);
    Route::post('finance/payroll/{payroll}/approve', [PayrollController::class, 'approve'])->whereUuid('payroll');
    Route::post('finance/payroll/{payroll}/pay', [PayrollController::class, 'pay'])->whereUuid('payroll');
    Route::get('my-staff-profile', [PayrollController::class, 'mine']);
    Route::get('finance/trial-balance', [AccountingController::class, 'trialBalance']);
    Route::get('finance/journals', [AccountingController::class, 'journals']);
});
