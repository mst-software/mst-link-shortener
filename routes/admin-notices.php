<?php

use App\Http\Controllers\Admin\NoticeController;
use App\Http\Controllers\Admin\SupportMessageController;
use Illuminate\Support\Facades\Route;

/*
 * Admin routes for notices and the support inbox. Required from routes/admin.php.
 */
Route::middleware(['auth', 'active', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('notices', NoticeController::class)->except('show');

    Route::get('support-messages', [SupportMessageController::class, 'index'])->name('support-messages.index');
    Route::patch('support-messages/{supportMessage}/resolve', [SupportMessageController::class, 'resolve'])->name('support-messages.resolve');
});
