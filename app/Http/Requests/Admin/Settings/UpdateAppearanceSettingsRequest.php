<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enums\BrandAsset;
use App\Services\MediaUploader;
use App\Support\BrandPalette;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The images are checked again (real content, decoding, pixel count) and re-encoded by MediaUploader;
 * these rules give operators a clear message for the common mistakes first. PHP's upload_max_filesize
 * and post_max_size must allow 5 MB per image, or larger uploads never reach the application.
 */
class UpdateAppearanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $extensions = implode(',', MediaUploader::ALLOWED_EXTENSIONS);

        $rules = [
            'brand_color' => ['required', 'string', 'regex:'.BrandPalette::HEX_PATTERN],
        ];

        foreach (BrandAsset::cases() as $asset) {
            $rules[$asset->value] = ['nullable', 'file', 'extensions:'.$extensions, 'mimes:'.$extensions, 'max:'.MediaUploader::MAX_KILOBYTES];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'brand_color.regex' => 'Choose a colour in the form #RRGGBB, for example #038018.',
        ];

        foreach (BrandAsset::cases() as $asset) {
            $messages[$asset->value.'.extensions'] = 'Upload a JPG, PNG, WebP or GIF image. SVG files are not accepted.';
            $messages[$asset->value.'.mimes'] = 'The file is not a JPG, PNG, WebP or GIF image.';
            $messages[$asset->value.'.max'] = 'The image must not be larger than 5 MB.';
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['brand_color' => 'brand colour'];

        foreach (BrandAsset::cases() as $asset) {
            $attributes[$asset->value] = strtolower($asset->label());
        }

        return $attributes;
    }
}
