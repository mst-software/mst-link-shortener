<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PayoutRateController;
use App\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('payout-rates', [PayoutRateController::class, 'index'])->name('payout-rates');
Route::get('blog', [BlogController::class, 'index'])->name('blog');

Route::get('login', [LoginController::class, 'show'])->name('login');
Route::get('register', [RegisterController::class, 'show'])->name('register');
Route::get('forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');

Route::get('privacy', [LegalController::class, 'privacy'])->name('privacy');
Route::get('terms', [LegalController::class, 'terms'])->name('terms');

/*
|--------------------------------------------------------------------------
| Visitor flow: KEEP THIS BLOCK LAST
|--------------------------------------------------------------------------
|
| "/{alias}" is a catch-all for short links. Every other route (for example
| "/{alias}/info" and "/links/*") must be registered ABOVE this comment.
|
*/

Route::post('links/get-link-info', [VisitController::class, 'info'])->name('visit.info');
Route::post('links/check-captcha', [VisitController::class, 'checkCaptcha'])->name('visit.check-captcha');
Route::post('links/report', [VisitController::class, 'report'])->middleware('throttle:5,1')->name('visit.report');
Route::get('go/{alias}', [VisitController::class, 'show'])->where('alias', '[A-Za-z0-9_-]{3,32}')->name('visit.show');
Route::get('{alias}', [VisitController::class, 'start'])->where('alias', '[A-Za-z0-9_-]{3,32}')->name('visit.start');
