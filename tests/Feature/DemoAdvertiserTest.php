<?php

use Illuminate\Support\Facades\Route;

/**
 * routes/web.php loads routes/demo.php at boot only when app()->isLocal(), and the test app boots as "testing".
 * To test the local case we switch the environment and load the same file the way web.php does.
 */
function registerDemoRoutes(string $environment): void
{
    app()->detectEnvironment(fn () => $environment);

    if (app()->isLocal()) {
        Route::middleware('web')->group(base_path('routes/demo.php'));
        Route::getRoutes()->refreshNameLookups();
    }
}

test('demo advertiser pages render the widget script in the local environment', function (string $path) {
    registerDemoRoutes('local');

    $this->get($path)
        ->assertOk()
        ->assertSee('<script async src="'.url('/js/widget.js').'" data-campaign="demo-campaign-key-0001"></script>', false)
        ->assertSee('Demo advertiser site: local testing only');
})->with([
    'page 1' => '/demo/advertiser',
    'page 2' => '/demo/advertiser/page-2',
    'page 3' => '/demo/advertiser/page-3',
]);

test('demo advertiser pages link to each other', function () {
    registerDemoRoutes('local');

    $this->get('/demo/advertiser')
        ->assertSee(url('/demo/advertiser/page-2'), false)
        ->assertSee(url('/demo/advertiser/page-3'), false);
});

test('demo advertiser routes are not registered outside the local environment', function () {
    registerDemoRoutes('production');

    $this->get('/demo/advertiser')->assertNotFound();
    $this->get('/demo/advertiser/page-2')->assertNotFound();
});
