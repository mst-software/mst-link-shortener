<?php

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/admin-notices.php';
require __DIR__.'/admin-withdrawals.php';
require __DIR__.'/admin-settings.php';
require __DIR__.'/admin-ads.php';
require __DIR__.'/admin-security.php';
