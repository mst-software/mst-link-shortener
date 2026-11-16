<?php

use App\Enums\FootprintSignal;
use App\Models\User;
use App\Models\UserLogin;
use App\Services\MultiAccountDetector;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const DETECTOR_UA = 'Mozilla/5.0 DetectorTest/1.0';

test('a referred account with the same sign-up ip and browser scores high', function () {
    $referrer = User::factory()->create();
    $referred = User::factory()->create(['referred_by' => $referrer->id]);
    UserLogin::factory()->signup()->for($referrer)->withUserAgent(DETECTOR_UA)->create(['ip' => '198.51.100.10']);
    UserLogin::factory()->signup()->for($referred)->withUserAgent(DETECTOR_UA)->create(['ip' => '198.51.100.10']);

    $related = app(MultiAccountDetector::class)->relatedAccounts($referrer);

    expect($related)->toHaveCount(1)
        ->and($related->first()->user->id)->toBe($referred->id)
        ->and($related->first()->signals)->toBe([FootprintSignal::SameSignupIp, FootprintSignal::SameDevice, FootprintSignal::ReferralRelation])
        ->and($related->first()->score())->toBe(95)
        ->and($related->first()->level())->toBe('high')
        ->and($related->first()->lastIp)->toBe('198.51.100.10');
});

test('a shared sign-in ip alone scores low and unrelated accounts are not listed', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    User::factory()->create();
    UserLogin::factory()->for($user)->withUserAgent('Browser A')->create(['ip' => '198.51.100.20']);
    UserLogin::factory()->for($other)->withUserAgent('Browser B')->create(['ip' => '198.51.100.20']);
    UserLogin::factory()->withUserAgent('Browser C')->create(['ip' => '198.51.100.99']);

    $related = app(MultiAccountDetector::class)->relatedAccounts($user);

    expect($related)->toHaveCount(1)
        ->and($related->first()->signals)->toBe([FootprintSignal::SharedLoginIp])
        ->and($related->first()->score())->toBe(20)
        ->and($related->first()->level())->toBe('low');
});

test('a referral without any shared footprint is not a signal', function () {
    $referrer = User::factory()->create();
    User::factory()->create(['referred_by' => $referrer->id]);
    UserLogin::factory()->signup()->for($referrer)->withUserAgent('Browser A')->create(['ip' => '198.51.100.1']);

    expect(app(MultiAccountDetector::class)->relatedAccounts($referrer))->toBeEmpty();
});

test('an ip shared by more accounts than the limit is ignored as a shared network', function () {
    Settings::set('multi_account_shared_ip_limit', 3);
    $user = User::factory()->create();
    UserLogin::factory()->signup()->for($user)->withUserAgent('Browser 0')->create(['ip' => '198.51.100.30']);

    foreach (range(1, 3) as $index) {
        UserLogin::factory()->signup()->withUserAgent('Browser '.$index)->create(['ip' => '198.51.100.30']);
    }

    expect(app(MultiAccountDetector::class)->relatedAccounts($user))->toBeEmpty();

    Settings::set('multi_account_shared_ip_limit', 4);

    expect(app(MultiAccountDetector::class)->relatedAccounts($user))->toHaveCount(3);
});

test('sign-in footprints older than the lookback window are ignored but the sign-up ip is kept', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $old = now()->subDays(MultiAccountDetector::LOOKBACK_DAYS + 1);
    UserLogin::factory()->signup()->for($user)->withUserAgent('Browser A')->create(['ip' => '198.51.100.40', 'created_at' => $old]);
    UserLogin::factory()->signup()->for($other)->withUserAgent('Browser B')->create(['ip' => '198.51.100.40', 'created_at' => $old]);
    UserLogin::factory()->for($user)->withUserAgent('Browser A')->create(['ip' => '198.51.100.41', 'created_at' => $old]);
    UserLogin::factory()->for($other)->withUserAgent('Browser A')->create(['ip' => '198.51.100.41', 'created_at' => $old]);

    $related = app(MultiAccountDetector::class)->compare($user, $other);

    expect($related?->signals)->toBe([FootprintSignal::SameSignupIp]);
});
