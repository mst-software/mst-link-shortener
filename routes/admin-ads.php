<?php

use App\Http\Controllers\Admin\AdSlotController;
use Illuminate\Support\Facades\Route;

/*
 * Admin routes for ad slots. Required from routes/admin.php.
 */
Route::middleware(['auth', 'active', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('ad-slots', AdSlotController::class)->except('show')->parameters(['ad-slots' => 'adSlot']);
});
