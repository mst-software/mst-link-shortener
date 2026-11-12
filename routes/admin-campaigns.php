<?php

use App\Http\Controllers\Admin\CampaignController;
use Illuminate\Support\Facades\Route;

/*
 * Admin routes for advertiser campaigns. Required from routes/admin.php.
 */
Route::middleware(['auth', 'active', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('campaigns', CampaignController::class)->except('show');
    Route::patch('campaigns/{campaign}/toggle', [CampaignController::class, 'toggle'])->name('campaigns.toggle');
});
