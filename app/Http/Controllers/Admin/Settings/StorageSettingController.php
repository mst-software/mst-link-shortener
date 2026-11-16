<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Concerns\SavesSecretSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateStorageSettingsRequest;
use App\Services\Settings;
use App\Support\MediaStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StorageSettingController extends Controller
{
    use SavesSecretSettings;

    /**
     * Credentials shorter than this are not redacted from error messages, because replacing them would
     * mangle ordinary words.
     */
    private const MIN_REDACTED_LENGTH = 4;

    public function edit(): View
    {
        return view('admin.settings.storage', [
            'values' => [
                'upload_disk' => MediaStorage::currentDisk(),
                's3_key' => (string) config('filesystems.disks.s3.key'),
                's3_region' => (string) config('filesystems.disks.s3.region'),
                's3_bucket' => (string) config('filesystems.disks.s3.bucket'),
                's3_endpoint' => (string) config('filesystems.disks.s3.endpoint'),
                's3_url' => (string) config('filesystems.disks.s3.url'),
                's3_use_path_style' => (bool) config('filesystems.disks.s3.use_path_style_endpoint'),
            ],
            'disks' => MediaStorage::DISKS,
            'hasSecret' => Settings::string('s3_secret') !== '',
            'hasServerSecret' => filled(config('filesystems.disks.s3.secret')),
            'publicLinkExists' => file_exists(public_path('storage')),
        ]);
    }

    public function update(UpdateStorageSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Settings::set('upload_disk', $data['upload_disk']);
        Settings::set('s3_use_path_style', $data['s3_use_path_style'] ? '1' : '0');

        foreach (['s3_key', 's3_region', 's3_bucket', 's3_endpoint', 's3_url'] as $key) {
            Settings::set($key, $data[$key] ?? '');
        }

        $this->saveSecretSettings($data, UpdateStorageSettingsRequest::SECRET_KEYS);

        return redirect()->route('admin.settings.storage.edit')
            ->with('success', 'Storage settings saved. New uploads go to the selected location.');
    }

    /**
     * Write, read back and delete a small file on the saved S3 bucket and report the real error.
     */
    public function test(): RedirectResponse
    {
        $bucket = (string) config('filesystems.disks.s3.bucket');

        if ($bucket === '') {
            return redirect()->route('admin.settings.storage.edit')
                ->with('error', 'Save the S3 settings first, then test the connection.');
        }

        $disk = Storage::disk(MediaStorage::DISK_S3);
        $path = 'connection-test/'.Str::random(40).'.txt';
        $contents = 'Storage connection test '.now()->toIso8601String();

        try {
            if (! $disk->put($path, $contents)) {
                throw new RuntimeException('The test file could not be written.');
            }

            if ($disk->get($path) !== $contents) {
                throw new RuntimeException('The test file was written but could not be read back.');
            }

            $disk->delete($path);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.settings.storage.edit')
                ->with('error', 'The connection test failed: '.$this->withoutCredentials($exception->getMessage()));
        }

        return redirect()->route('admin.settings.storage.edit')
            ->with('success', 'Connected to the bucket "'.$bucket.'": a test file was written, read back and deleted.');
    }

    /**
     * Hide the access key and secret if the storage service echoed them back in its error message.
     */
    private function withoutCredentials(string $message): string
    {
        foreach (['key', 'secret'] as $credential) {
            $value = (string) config('filesystems.disks.s3.'.$credential);

            if (mb_strlen($value) >= self::MIN_REDACTED_LENGTH) {
                $message = str_replace($value, '********', $message);
            }
        }

        return $message;
    }
}
