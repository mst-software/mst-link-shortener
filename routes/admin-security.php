<?php

use App\Http\Controllers\Admin\BlockedNetworkController;
use App\Http\Controllers\Admin\FraudFlagController;
use App\Http\Controllers\Admin\LinkController;
use Illuminate\Support\Facades\Route;

/*
 * Admin routes for blocked networks, fraud flags and link bans. Required from routes/admin.php.
 */
Route::middleware(['auth', 'active', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('blocked-networks', BlockedNetworkController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['blocked-networks' => 'blockedNetwork']);

    Route::get('fraud-flags', [FraudFlagController::class, 'index'])->name('fraud-flags.index');
    Route::patch('fraud-flags/{fraudFlag}/resolve', [FraudFlagController::class, 'resolve'])->name('fraud-flags.resolve');
    Route::post('fraud-flags/{fraudFlag}/ban-link', [FraudFlagController::class, 'banLink'])->name('fraud-flags.ban-link');

    Route::get('links', [LinkController::class, 'index'])->name('links.index');
    Route::post('links/{link}/ban', [LinkController::class, 'ban'])->name('links.ban');
});
