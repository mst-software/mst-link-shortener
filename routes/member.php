<?php

use App\Http\Controllers\Member\DashboardController;
use App\Http\Controllers\Member\LinkController;
use App\Http\Controllers\Member\NotificationController;
use App\Http\Controllers\Member\PopularLinkController;
use App\Http\Controllers\Member\ReferralController;
use App\Http\Controllers\Member\StatisticController;
use App\Http\Controllers\Member\SupportController;
use App\Http\Controllers\Member\ToolController;
use App\Http\Controllers\Member\UserController;
use App\Http\Controllers\Member\WithdrawController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->prefix('member')->name('member.')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('notify', [NotificationController::class, 'index'])->name('notify');
    Route::get('statistic', [StatisticController::class, 'index'])->name('statistic');
    Route::get('popular-link', [PopularLinkController::class, 'index'])->name('popular-link');

    Route::match(['get', 'post'], 'links', [LinkController::class, 'index'])->name('links');
    Route::get('links/hidden', [LinkController::class, 'hidden'])->name('links.hidden');

    Route::get('withdraws', [WithdrawController::class, 'index'])->name('withdraws');

    Route::prefix('tools')->name('tools.')->group(function () {
        Route::get('quick', [ToolController::class, 'quick'])->name('quick');
        Route::get('mass-shrinker', [ToolController::class, 'massShrinker'])->name('mass-shrinker');
        Route::get('full', [ToolController::class, 'fullPageScript'])->name('full');
        Route::get('api', [ToolController::class, 'api'])->name('api');
    });

    Route::get('referrals', [ReferralController::class, 'index'])->name('referrals');

    Route::prefix('user')->name('user.')->group(function () {
        Route::get('profile', [UserController::class, 'profile'])->name('profile');
        Route::get('change-password', [UserController::class, 'changePassword'])->name('change-password');
        Route::get('change-email', [UserController::class, 'changeEmail'])->name('change-email');
    });

    Route::get('form/support', [SupportController::class, 'show'])->name('form.support');
});
