<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks users without a verification date as verified and keeps existing dates', function () {
    $unverified = User::factory()->unverified()->create();
    $verifiedAt = now()->subYear()->startOfSecond();
    $verified = User::factory()->create(['email_verified_at' => $verifiedAt]);

    $migration = require database_path('migrations/2026_11_16_002116_mark_existing_users_email_verified.php');
    $migration->up();

    expect($unverified->refresh()->email_verified_at)->not->toBeNull()
        ->and($verified->refresh()->email_verified_at->equalTo($verifiedAt))->toBeTrue();
});
