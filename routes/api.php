<?php

use App\Http\Controllers\Api\ShortenController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function () {
    Route::get('api', ShortenController::class)->name('api.shorten');
    Route::get('api-shorten/v2', ShortenController::class)->name('api.shorten.v2');
});
