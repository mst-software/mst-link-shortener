<?php

use App\Http\Controllers\Admin\AdvertiserController;
use Illuminate\Support\Facades\Route;

/*
 * Admin routes for advertisers and their top ups. Required from routes/admin.php.
 */
Route::middleware(['auth', 'active', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('advertisers', [AdvertiserController::class, 'index'])->name('advertisers.index');
    Route::get('advertisers/{advertiser}', [AdvertiserController::class, 'show'])->name('advertisers.show');
    Route::patch('advertisers/{advertiser}/toggle', [AdvertiserController::class, 'toggle'])->name('advertisers.toggle');
    Route::post('top-ups/{topUp}/credit', [AdvertiserController::class, 'creditTopUp'])->name('top-ups.credit');
});
