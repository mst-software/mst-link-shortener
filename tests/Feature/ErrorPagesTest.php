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

it('renders the not found page for an unknown address', function () {
    $this->get('testing/no/such/page')->assertNotFound()->assertSee('Page not found');
});
