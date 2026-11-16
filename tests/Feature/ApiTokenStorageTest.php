<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * The raw users row, without model casts.
 */
function rawUserRow(User $user): object
{
    return DB::table('users')->where('id', $user->id)->first();
}

/**
 * The migration that hashes and encrypts the API tokens.
 */
function apiTokenMigration(): object
{
    return require database_path('migrations/2026_11_16_030934_hash_and_encrypt_user_api_tokens.php');
}

it('stores the api token encrypted with a sha256 hash for the lookup', function () {
    $user = User::factory()->create();
    $row = rawUserRow($user);

    expect($row->api_token)->not->toBe($user->api_token)
        ->and(Crypt::decryptString($row->api_token))->toBe($user->api_token)
        ->and($row->api_token_hash)->toBe(hash('sha256', $user->api_token));
});

it('finds the owner of an api token by its hash', function () {
    $user = User::factory()->create();

    expect(User::findByApiToken($user->api_token)?->is($user))->toBeTrue()
        ->and(User::findByApiToken('unknown-token'))->toBeNull();
});

it('does not look the token up in the token column', function () {
    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['api_token_hash' => hash('sha256', 'another-token')]);

    expect(User::findByApiToken($user->api_token))->toBeNull()
        ->and(User::findByApiToken('another-token')?->is($user))->toBeTrue();
});

it('keeps the hash in step when the token is regenerated', function () {
    $user = User::factory()->create();
    $oldToken = $user->api_token;

    $newToken = $user->regenerateApiToken();

    expect($newToken)->not->toBe($oldToken)->toHaveLength(24)
        ->and(rawUserRow($user)->api_token_hash)->toBe(hash('sha256', $newToken))
        ->and(User::findByApiToken($oldToken))->toBeNull()
        ->and(User::findByApiToken($newToken)?->is($user))->toBeTrue();
});

it('does not change the hash when other attributes are saved', function () {
    $user = User::factory()->create();
    $hash = rawUserRow($user)->api_token_hash;

    $user->forceFill(['fullname' => 'Jane Doe'])->save();

    expect(rawUserRow($user)->api_token_hash)->toBe($hash);
});

it('never serializes the api token or its hash', function () {
    $user = User::factory()->create();

    expect($user->toArray())->not->toHaveKeys(['api_token', 'api_token_hash'])
        ->and($user->toJson())->not->toContain($user->api_token)
        ->not->toContain(rawUserRow($user)->api_token_hash);
});

it('backfills plain tokens and leaves converted ones alone when run again', function () {
    $legacy = User::factory()->create();
    DB::table('users')->where('id', $legacy->id)->update(['api_token' => 'legacyPlainToken12345678', 'api_token_hash' => null]);
    $converted = User::factory()->create();
    $convertedRow = rawUserRow($converted);

    apiTokenMigration()->up();
    $legacyRow = rawUserRow($legacy);
    apiTokenMigration()->up();

    expect(Crypt::decryptString($legacyRow->api_token))->toBe('legacyPlainToken12345678')
        ->and($legacyRow->api_token_hash)->toBe(hash('sha256', 'legacyPlainToken12345678'))
        ->and(rawUserRow($legacy))->toEqual($legacyRow)
        ->and(rawUserRow($converted))->toEqual($convertedRow)
        ->and(User::findByApiToken('legacyPlainToken12345678')?->is($legacy))->toBeTrue();
});

it('restores plain tokens when rolled back', function () {
    $user = User::factory()->create();
    $token = $user->api_token;

    apiTokenMigration()->down();

    expect(Schema::hasColumn('users', 'api_token_hash'))->toBeFalse()
        ->and(rawUserRow($user)->api_token)->toBe($token);

    apiTokenMigration()->up();

    expect(User::findByApiToken($token)?->is($user))->toBeTrue();
});
