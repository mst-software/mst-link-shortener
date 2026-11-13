<?php

use App\Http\Controllers\Member\DashboardController;
use App\Http\Controllers\Member\EmailController;
use App\Http\Controllers\Member\LinkController;
use App\Http\Controllers\Member\NoticeController;
use App\Http\Controllers\Member\PasswordController;
use App\Http\Controllers\Member\PopularLinkController;
use App\Http\Controllers\Member\ProfileController;
use App\Http\Controllers\Member\ReferralController;
use App\Http\Controllers\Member\StatisticController;
use App\Http\Controllers\Member\SupportController;
use App\Http\Controllers\Member\ToolController;
use App\Http\Controllers\Member\WithdrawController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active', 'not-advertiser'])->prefix('member')->name('member.')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('dashboard/get-data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('notify', [NoticeController::class, 'index'])->name('notify');
    Route::get('statistic', [StatisticController::class, 'index'])->name('statistic');
    Route::get('popular-link', [PopularLinkController::class, 'index'])->name('popular-link');

    Route::match(['get', 'post'], 'links', [LinkController::class, 'index'])->name('links');
    Route::match(['get', 'post'], 'links/hidden', [LinkController::class, 'hidden'])->name('links.hidden');
    Route::post('links/hide', [LinkController::class, 'hide'])->name('links.hide');
    Route::post('links/restore', [LinkController::class, 'restore'])->name('links.restore');

    Route::get('withdraws', [WithdrawController::class, 'index'])->name('withdraws');
    Route::post('withdraws', [WithdrawController::class, 'store'])->name('withdraws.store');

    Route::prefix('tools')->name('tools.')->group(function () {
        Route::get('quick', [ToolController::class, 'quick'])->name('quick');
        Route::get('mass-shrinker', [ToolController::class, 'massShrinker'])->name('mass-shrinker');
        Route::post('mass-shrinker', [ToolController::class, 'massShrink'])->name('mass-shrinker.submit');
        Route::get('full', [ToolController::class, 'fullPageScript'])->name('full');
        Route::get('api', [ToolController::class, 'api'])->name('api');
    });

    Route::get('referrals', [ReferralController::class, 'index'])->name('referrals');

    Route::prefix('user')->name('user.')->group(function () {
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile');
        Route::post('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('change-password', [PasswordController::class, 'edit'])->name('change-password');
        Route::post('change-password', [PasswordController::class, 'update'])->name('change-password.update');
        Route::get('change-email', [EmailController::class, 'edit'])->name('change-email');
        Route::post('change-email', [EmailController::class, 'update'])->name('change-email.update');
    });

    Route::get('form/support', [SupportController::class, 'show'])->name('form.support');
    Route::post('form/support', [SupportController::class, 'store'])->middleware('throttle:5,1')->name('form.support.store');
});
