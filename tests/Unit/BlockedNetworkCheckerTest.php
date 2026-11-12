<?php

use App\Models\BlockedNetwork;
use App\Services\Views\BlockedNetworkChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('blocks addresses inside an IPv4 range only', function () {
    BlockedNetwork::factory()->create(['cidr' => '10.0.0.0/24']);

    $checker = new BlockedNetworkChecker;

    expect($checker->isBlocked('10.0.0.5'))->toBeTrue()
        ->and($checker->isBlocked('10.0.1.5'))->toBeFalse();
});

it('matches ranges that are not on a byte boundary', function () {
    BlockedNetwork::factory()->create(['cidr' => '192.168.4.0/22']);

    $checker = new BlockedNetworkChecker;

    expect($checker->isBlocked('192.168.7.255'))->toBeTrue()
        ->and($checker->isBlocked('192.168.8.0'))->toBeFalse();
});

it('matches IPv6 ranges', function () {
    BlockedNetwork::factory()->create(['cidr' => '2001:db8::/32']);

    $checker = new BlockedNetworkChecker;

    expect($checker->isBlocked('2001:db8:1234::1'))->toBeTrue()
        ->and($checker->isBlocked('2001:db9::1'))->toBeFalse();
});

it('never matches across address families', function () {
    BlockedNetwork::factory()->create(['cidr' => '0.0.0.0/0']);

    expect((new BlockedNetworkChecker)->isBlocked('2001:db8::1'))->toBeFalse();
});

it('ignores malformed rows and malformed addresses', function () {
    BlockedNetwork::factory()->create(['cidr' => 'not-a-cidr']);
    BlockedNetwork::factory()->create(['cidr' => '10.0.0.0/99']);

    $checker = new BlockedNetworkChecker;

    expect($checker->isBlocked('10.0.0.1'))->toBeFalse()
        ->and($checker->isBlocked('garbage'))->toBeFalse();
});

it('caches the list until it is cleared', function () {
    $checker = new BlockedNetworkChecker;

    expect($checker->isBlocked('10.0.0.5'))->toBeFalse();

    BlockedNetwork::factory()->create(['cidr' => '10.0.0.0/24']);

    expect($checker->isBlocked('10.0.0.5'))->toBeFalse();

    $checker->clearCache();

    expect($checker->isBlocked('10.0.0.5'))->toBeTrue();
});
