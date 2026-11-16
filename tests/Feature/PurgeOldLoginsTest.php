<?php

use App\Models\UserLogin;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('logins:purge deletes footprints older than the retention period and keeps newer ones', function () {
    $old = UserLogin::factory()->signup()->create(['created_at' => now()->subDays(91)]);
    $recent = UserLogin::factory()->create(['created_at' => now()->subDays(89)]);

    $this->artisan('logins:purge')
        ->expectsOutput('Purged 1 login footprints.')
        ->assertSuccessful();

    expect(UserLogin::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(UserLogin::query()->whereKey($recent->id)->exists())->toBeTrue();
});

test('logins:purge follows the login_purge_days setting', function () {
    Settings::set('login_purge_days', 7);
    UserLogin::factory()->create(['created_at' => now()->subDays(8)]);
    UserLogin::factory()->create(['created_at' => now()->subDays(6)]);

    $this->artisan('logins:purge')->assertSuccessful();

    expect(UserLogin::query()->count())->toBe(1);
});
