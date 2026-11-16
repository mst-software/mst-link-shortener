<?php

use Illuminate\Support\Facades\Route;

/*
 * No RefreshDatabase on purpose: the error pages must render without any database table.
 */

beforeEach(function () {
    config(['app.debug' => false]);

    Route::get('testing/error/{status}', fn (int $status) => abort($status));
    Route::get('testing/crash', fn () => throw new RuntimeException('Secret internal detail.'));
});

it('renders a site styled page for each error status', function (int $status, string $title) {
    $this->get('testing/error/'.$status)
        ->assertStatus($status)
        ->assertSee($title)
        ->assertSee('Back to home')
        ->assertSee('noindex', false);
})->with([
    [403, 'Access denied'],
    [404, 'Page not found'],
    [419, 'Page expired'],
    [429, 'Too many requests'],
    [500, 'Something went wrong'],
    [503, 'Back soon'],
]);

it('shows no exception details on a server error', function () {
    $this->get('testing/crash')
        ->assertInternalServerError()
        ->assertSee('Something went wrong')
        ->assertDontSee('Secret internal detail.')
        ->assertDontSee('RuntimeException');
});

it('shows the message an access denial was given for visitors', function () {
    Route::get('testing/closed', fn () => abort(403, 'Sign-ups are <closed>.'));

    $this->get('testing/closed')->assertForbidden()->assertSee('Sign-ups are &lt;closed&gt;.', false);
});

it('renders the error pages with inline styling when the frontend build is missing', function (string $url, int $status, string $title) {
    $publicPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'error-pages-public-'.bin2hex(random_bytes(4));
    mkdir($publicPath);
    $this->app->usePublicPath($publicPath);
    $this->withVite();

    $this->get($url)
        ->assertStatus($status)
        ->assertSee($title)
        ->assertSee('<style>', false)
        ->assertDontSee('/build/', false);

    rmdir($publicPath);
})->with([
    'not found' => ['testing/no/such/page', 404, 'Page not found'],
    'server error' => ['testing/crash', 500, 'Something went wrong'],
]);

it('renders the not found page for an unknown address', function () {
    $this->get('testing/no/such/page')->assertNotFound()->assertSee('Page not found');
});
