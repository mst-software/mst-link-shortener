<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings;
use App\Support\MediaStorage;
use App\Support\SettingsConfigurator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;

uses(RefreshDatabase::class);

/**
 * A valid payload for the storage settings form, with optional overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function storageSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'upload_disk' => 's3',
        's3_key' => 'AKIAEXAMPLEKEY',
        's3_secret' => 'super-secret-access-key',
        's3_region' => 'auto',
        's3_bucket' => 'linkcash-media',
        's3_endpoint' => 'https://account.r2.cloudflarestorage.com',
        's3_url' => 'https://cdn.example.com',
        's3_use_path_style' => '0',
    ], $overrides);
}

beforeEach(function () {
    config([
        'filesystems.disks.s3.key' => null,
        'filesystems.disks.s3.secret' => null,
        'filesystems.disks.s3.bucket' => null,
    ]);

    $this->admin = User::factory()->admin()->create();
});

test('admins switch uploads to s3 and the settings configure the s3 disk', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.storage.update'), storageSettingsPayload(['s3_use_path_style' => '1']))
        ->assertRedirect(route('admin.settings.storage.edit'))
        ->assertSessionHas('success');

    expect(MediaStorage::currentDisk())->toBe('s3')
        ->and(Settings::get('s3_secret'))->toBe('super-secret-access-key')
        ->and(Setting::query()->where('key', 's3_secret')->value('value'))->not->toContain('super-secret-access-key');

    SettingsConfigurator::apply();

    expect(config('filesystems.disks.s3'))->toMatchArray([
        'key' => 'AKIAEXAMPLEKEY',
        'secret' => 'super-secret-access-key',
        'region' => 'auto',
        'bucket' => 'linkcash-media',
        'endpoint' => 'https://account.r2.cloudflarestorage.com',
        'url' => 'https://cdn.example.com',
        'use_path_style_endpoint' => true,
    ]);
});

test('admins switch uploads back to this server without s3 details', function () {
    Settings::set('upload_disk', 's3');

    $this->actingAs($this->admin)
        ->put(route('admin.settings.storage.update'), storageSettingsPayload([
            'upload_disk' => 'public', 's3_key' => '', 's3_secret' => '', 's3_region' => '', 's3_bucket' => '', 's3_endpoint' => '', 's3_url' => '',
        ]))
        ->assertSessionHasNoErrors();

    expect(MediaStorage::currentDisk())->toBe('public');
});

test('the secret is write-only and a blank secret keeps the saved one', function () {
    Settings::set('s3_secret', 'super-secret-access-key');
    config(['filesystems.disks.s3.secret' => 'super-secret-access-key']);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.storage.update'), storageSettingsPayload(['s3_secret' => '']))
        ->assertSessionHasNoErrors();

    expect(Settings::get('s3_secret'))->toBe('super-secret-access-key');

    $this->actingAs($this->admin)
        ->get(route('admin.settings.storage.edit'))
        ->assertOk()
        ->assertSee('Saved')
        ->assertDontSee('super-secret-access-key');
});

test('invalid storage settings are rejected', function (array $overrides, string $field) {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.storage.update'), storageSettingsPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(Settings::has('upload_disk'))->toBeFalse();
})->with([
    'unknown disk' => [['upload_disk' => 'ftp'], 'upload_disk'],
    'missing key for s3' => [['s3_key' => ''], 's3_key'],
    'missing secret for s3' => [['s3_secret' => ''], 's3_secret'],
    'missing bucket for s3' => [['s3_bucket' => ''], 's3_bucket'],
    'bucket with a path' => [['s3_bucket' => 'media/../other'], 's3_bucket'],
    'region with spaces' => [['s3_region' => 'us east'], 's3_region'],
    'endpoint without a scheme' => [['s3_endpoint' => 'account.r2.cloudflarestorage.com'], 's3_endpoint'],
    'public address with a javascript scheme' => [['s3_url' => 'javascript:alert(1)'], 's3_url'],
]);

test('the connection test writes, reads and deletes a file', function () {
    config(['filesystems.disks.s3.bucket' => 'linkcash-media']);
    Storage::fake('s3');

    $this->actingAs($this->admin)
        ->post(route('admin.settings.storage.test'))
        ->assertRedirect(route('admin.settings.storage.edit'))
        ->assertSessionHas('success', 'Connected to the bucket "linkcash-media": a test file was written, read back and deleted.');

    expect(Storage::disk('s3')->allFiles())->toBeEmpty();
});

test('a failing connection test shows the storage error without the credentials', function () {
    Exceptions::fake();
    config([
        'filesystems.disks.s3.bucket' => 'linkcash-media',
        'filesystems.disks.s3.key' => 'AKIAEXAMPLEKEY',
        'filesystems.disks.s3.secret' => 'super-secret-access-key',
    ]);
    Storage::set('s3', Mockery::mock(Filesystem::class, function ($mock): void {
        $mock->shouldReceive('put')->andThrow(UnableToWriteFile::atLocation('connection-test/x.txt', 'Access Denied for AKIAEXAMPLEKEY / super-secret-access-key'));
    }));

    $this->actingAs($this->admin)
        ->post(route('admin.settings.storage.test'))
        ->assertRedirect(route('admin.settings.storage.edit'))
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'The connection test failed:')
            && str_contains($message, 'Access Denied for ******** / ********')
            && ! str_contains($message, 'super-secret-access-key'));

    Exceptions::assertReported(UnableToWriteFile::class);
});

test('the connection test asks for the settings first when no bucket is saved', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.settings.storage.test'))
        ->assertSessionHas('error', 'Save the S3 settings first, then test the connection.');
});

test('the connection test is rate limited', function () {
    config(['filesystems.disks.s3.bucket' => 'linkcash-media']);
    Storage::fake('s3');

    foreach (range(1, 6) as $attempt) {
        $this->actingAs($this->admin)->post(route('admin.settings.storage.test'))->assertRedirect();
    }

    $this->actingAs($this->admin)->post(route('admin.settings.storage.test'))->assertTooManyRequests();
});

test('members cannot change the storage settings or test the connection', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->put(route('admin.settings.storage.update'), storageSettingsPayload())->assertForbidden();
    $this->actingAs($member)->post(route('admin.settings.storage.test'))->assertForbidden();

    expect(Settings::has('upload_disk'))->toBeFalse();
});
