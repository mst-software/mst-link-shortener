<?php

namespace App\Http\Requests\Admin\Settings;

use App\Services\Settings;
use App\Support\MediaStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStorageSettingsRequest extends FormRequest
{
    /**
     * Write-only settings of this page.
     *
     * @var list<string>
     */
    public const SECRET_KEYS = ['s3_secret'];

    /**
     * Region names such as us-east-1, or "auto" for Cloudflare R2.
     */
    private const REGION_PATTERN = '/^[a-z0-9-]{2,32}$/';

    /**
     * S3 bucket names: 3 to 63 lowercase letters, digits, dots and hyphens.
     */
    private const BUCKET_PATTERN = '/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'upload_disk' => ['required', Rule::in(array_keys(MediaStorage::DISKS))],
            's3_key' => ['required_if:upload_disk,s3', 'nullable', 'string', 'max:255'],
            's3_secret' => ['nullable', 'string', 'max:'.Settings::SECRET_MAX_LENGTH],
            'clear_s3_secret' => ['nullable', 'boolean'],
            's3_region' => ['required_if:upload_disk,s3', 'nullable', 'string', 'regex:'.self::REGION_PATTERN],
            's3_bucket' => ['required_if:upload_disk,s3', 'nullable', 'string', 'regex:'.self::BUCKET_PATTERN],
            's3_endpoint' => ['nullable', 'string', 'max:255', 'url:https,http'],
            's3_url' => ['nullable', 'string', 'max:255', 'url:https,http'],
            's3_use_path_style' => ['required', 'boolean'],
        ];
    }

    /**
     * S3 needs a secret: either a new one in this form or one already in effect (saved here or set in
     * .env) that is not being removed.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('upload_disk') !== MediaStorage::DISK_S3 || filled($this->input('s3_secret'))) {
                    return;
                }

                if ($this->boolean('clear_s3_secret') || blank(config('filesystems.disks.s3.secret'))) {
                    $validator->errors()->add('s3_secret', 'Enter the secret access key to store files on S3.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            's3_key.required_if' => 'Enter the access key ID to store files on S3.',
            's3_region.required_if' => 'Enter the region to store files on S3 (use "auto" for Cloudflare R2).',
            's3_region.regex' => 'Enter a region such as us-east-1, or "auto" for Cloudflare R2.',
            's3_bucket.required_if' => 'Enter the bucket name to store files on S3.',
            's3_bucket.regex' => 'Enter a bucket name of 3 to 63 lowercase letters, digits, dots or hyphens.',
            's3_endpoint.url' => 'Enter the full endpoint address, starting with https://.',
            's3_url.url' => 'Enter the full public address, starting with https://.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'upload_disk' => 'storage location',
            's3_key' => 'access key ID',
            's3_secret' => 'secret access key',
            's3_region' => 'region',
            's3_bucket' => 'bucket',
            's3_endpoint' => 'endpoint',
            's3_url' => 'public address',
            's3_use_path_style' => 'path-style addresses',
        ];
    }
}
