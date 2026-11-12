<?php

use App\Http\Controllers\Admin\CpmRateController;
use App\Http\Controllers\Admin\SettingController;
use Illuminate\Support\Facades\Route;

/*
 * Admin routes for platform settings and publisher rates. Required from routes/admin.php.
 */
Route::middleware(['auth', 'active', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

    Route::get('rates', [CpmRateController::class, 'index'])->name('rates.index');
    Route::post('rates', [CpmRateController::class, 'store'])->name('rates.store');
    Route::put('rates/{country}', [CpmRateController::class, 'update'])->where('country', '[A-Za-z]{2}')->name('rates.update');
    Route::delete('rates/{country}', [CpmRateController::class, 'destroy'])->where('country', '[A-Za-z]{2}')->name('rates.destroy');
});
