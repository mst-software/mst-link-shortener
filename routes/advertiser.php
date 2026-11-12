<?php

use App\Http\Controllers\Advertiser\DashboardController;
use App\Http\Controllers\Auth\AdvertiserRegisterController;
use Illuminate\Support\Facades\Route;

Route::get('advertiser/signup', [AdvertiserRegisterController::class, 'show'])->name('advertiser.register');
Route::post('advertiser/signup', [AdvertiserRegisterController::class, 'store'])->middleware('throttle:register');

Route::middleware(['auth', 'active', 'advertiser'])->prefix('advertiser')->name('advertiser.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
});
