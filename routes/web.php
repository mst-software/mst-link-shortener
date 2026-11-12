<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LinkInfoController;
use App\Http\Controllers\LinkShortenController;
use App\Http\Controllers\PayoutRateController;
use App\Http\Controllers\QuickLinkController;
use App\Http\Controllers\ReferralRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('payout-rates', [PayoutRateController::class, 'index'])->name('payout-rates');
Route::get('blog', [BlogController::class, 'index'])->name('blog');

Route::get('auth/signin', [LoginController::class, 'show'])->name('login');
Route::post('auth/signin', [LoginController::class, 'store']);
Route::get('auth/signup', [RegisterController::class, 'show'])->name('register');
Route::post('auth/signup', [RegisterController::class, 'store']);
Route::post('auth/signout', LogoutController::class)->name('logout');
Route::get('auth/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
Route::post('auth/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
Route::get('auth/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
Route::post('auth/reset-password', [ResetPasswordController::class, 'update'])->name('password.update');

Route::get('pages/privacy', [LegalController::class, 'privacy'])->name('privacy');
Route::get('pages/terms', [LegalController::class, 'terms'])->name('terms');

Route::get('ref/{code}', ReferralRedirectController::class)->name('referral');

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('links/shorten', LinkShortenController::class)->name('links.shorten');
    Route::get('{alias}/info', LinkInfoController::class)->name('link.info');
});

Route::get('st', QuickLinkController::class)->middleware('throttle:api')->name('quick-link');

// visit routes are registered below this line (Task 15)
