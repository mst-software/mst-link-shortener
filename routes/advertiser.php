<?php

use App\Http\Controllers\Advertiser\CampaignController;
use App\Http\Controllers\Advertiser\DashboardController;
use App\Http\Controllers\Advertiser\TopUpController;
use App\Http\Controllers\Advertiser\WalletController;
use App\Http\Controllers\Auth\AdvertiserRegisterController;
use Illuminate\Support\Facades\Route;

Route::get('advertiser/signup', [AdvertiserRegisterController::class, 'show'])->name('advertiser.register');
Route::post('advertiser/signup', [AdvertiserRegisterController::class, 'store'])->middleware('throttle:register');

Route::middleware(['auth', 'active', 'advertiser'])->prefix('advertiser')->name('advertiser.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('campaigns', CampaignController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::get('wallet', [WalletController::class, 'index'])->name('wallet');
    Route::post('topups/sepay', [TopUpController::class, 'sepay'])->name('topups.sepay');

    Route::patch('campaigns/{campaign}/toggle', [CampaignController::class, 'toggle'])->name('campaigns.toggle');
});
