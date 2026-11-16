<?php

use App\Http\Controllers\Admin\CpmRateController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\Settings\AppearanceSettingController;
use App\Http\Controllers\Admin\Settings\CaptchaSettingController;
use App\Http\Controllers\Admin\Settings\EmailSettingController;
use App\Http\Controllers\Admin\Settings\GeneralSettingController;
use App\Http\Controllers\Admin\Settings\PaymentSettingController;
use App\Http\Controllers\Admin\Settings\StorageSettingController;
use App\Http\Controllers\Admin\Settings\TrafficSettingController;
use Illuminate\Support\Facades\Route;

/*
 * Admin routes for platform settings and publisher rates. Required from routes/admin.php.
 * Each settings page is listed in App\Enums\SettingsPage and named "admin.settings.{page}.edit".
 */
Route::middleware(['auth', 'active', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('general', [GeneralSettingController::class, 'edit'])->name('general.edit');
        Route::put('general', [GeneralSettingController::class, 'update'])->name('general.update');

        Route::get('appearance', [AppearanceSettingController::class, 'edit'])->name('appearance.edit');
        Route::put('appearance', [AppearanceSettingController::class, 'update'])->name('appearance.update');
        Route::delete('appearance/images/{asset}', [AppearanceSettingController::class, 'resetImage'])->name('appearance.images.reset');
        Route::delete('appearance/color', [AppearanceSettingController::class, 'resetColor'])->name('appearance.color.reset');

        Route::get('email', [EmailSettingController::class, 'edit'])->name('email.edit');
        Route::put('email', [EmailSettingController::class, 'update'])->name('email.update');
        Route::post('email/test', [EmailSettingController::class, 'test'])->middleware('throttle:6,1')->name('email.test');

        Route::get('payments', [PaymentSettingController::class, 'edit'])->name('payments.edit');
        Route::put('payments', [PaymentSettingController::class, 'update'])->name('payments.update');

        Route::get('traffic', [TrafficSettingController::class, 'edit'])->name('traffic.edit');
        Route::put('traffic', [TrafficSettingController::class, 'update'])->name('traffic.update');

        Route::get('captcha', [CaptchaSettingController::class, 'edit'])->name('captcha.edit');
        Route::put('captcha', [CaptchaSettingController::class, 'update'])->name('captcha.update');

        Route::get('storage', [StorageSettingController::class, 'edit'])->name('storage.edit');
        Route::put('storage', [StorageSettingController::class, 'update'])->name('storage.update');
        Route::post('storage/test', [StorageSettingController::class, 'test'])->middleware('throttle:6,1')->name('storage.test');
    });

    Route::get('rates', [CpmRateController::class, 'index'])->name('rates.index');
    Route::post('rates', [CpmRateController::class, 'store'])->name('rates.store');
    Route::put('rates/{country}', [CpmRateController::class, 'update'])->where('country', '[A-Za-z]{2}')->name('rates.update');
    Route::delete('rates/{country}', [CpmRateController::class, 'destroy'])->where('country', '[A-Za-z]{2}')->name('rates.destroy');
});
