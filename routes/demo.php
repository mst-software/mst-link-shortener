<?php

use App\Http\Controllers\DemoAdvertiserController;
use Illuminate\Support\Facades\Route;

// Local-only fake advertiser site used to try the keyword code widget. Loaded from routes/web.php.
Route::get('demo/advertiser', [DemoAdvertiserController::class, 'show'])->defaults('page', 1)->name('demo.advertiser');
Route::get('demo/advertiser/page-{page}', [DemoAdvertiserController::class, 'show'])->whereIn('page', ['2', '3'])->name('demo.advertiser.page');
