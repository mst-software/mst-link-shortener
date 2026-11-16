<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserBalanceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserEmailController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Admin\UserStatusController;
use Illuminate\Support\Facades\Route;

/*
 * Admin routes for managing user accounts and reading the admin audit log. Required from routes/admin.php.
 */
Route::middleware(['auth', 'active', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');

    Route::post('users/{user}/ban', [UserStatusController::class, 'ban'])->name('users.ban');
    Route::post('users/{user}/unban', [UserStatusController::class, 'unban'])->name('users.unban');

    Route::post('users/{user}/verify-email', [UserEmailController::class, 'verify'])->name('users.verify-email');
    Route::post('users/{user}/verification-email', [UserEmailController::class, 'sendVerification'])->name('users.verification-email');
    Route::post('users/{user}/password-reset', [UserEmailController::class, 'sendPasswordReset'])->name('users.password-reset');

    Route::patch('users/{user}/role', [UserRoleController::class, 'update'])->name('users.role');
    Route::post('users/{user}/balance-adjustments', [UserBalanceController::class, 'store'])->name('users.balance-adjustments.store');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});
