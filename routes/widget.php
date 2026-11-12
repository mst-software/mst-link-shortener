<?php

use App\Http\Controllers\WidgetController;
use App\Http\Middleware\WidgetCors;
use Illuminate\Support\Facades\Route;

// CSRF is skipped for widget/* in bootstrap/app.php: these requests come from other sites.
Route::prefix('widget')
    ->middleware(['throttle:60,1', WidgetCors::class])
    ->group(function () {
        Route::post('start', [WidgetController::class, 'start'])->middleware('throttle:widget-start')->name('widget.start');
        Route::post('progress', [WidgetController::class, 'progress'])->name('widget.progress');
        Route::post('code', [WidgetController::class, 'code'])->name('widget.code');
        Route::options('{any}', fn () => response()->noContent())->where('any', '.*')->name('widget.options');
    });
