<?php

namespace App\Services;

use App\Enums\BrandAsset;
use App\Support\BrandPalette;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Vite;

/**
 * The brand images and colour chosen on the admin Appearance settings page. While nothing is uploaded or
 * chosen, the bundled images in resources/images and the colours of resources/css/tokens.css apply.
 */
class Branding
{
    /**
     * The colour of --color-brand-600 in resources/css/tokens.css.
     */
    public const DEFAULT_COLOR = '#038018';

    private const COLOR_KEY = 'brand_color';

    private const CSS_CACHE_KEY = 'branding.css';

    /**
     * URL of the uploaded image, or of the bundled default; null for an optional image that is not set.
     */
    public static function imageUrl(BrandAsset $asset): ?string
    {
        $uploaded = self::uploadedImageUrl($asset);

        if ($uploaded !== null) {
            return $uploaded;
        }

        $default = $asset->defaultImage();

        return $default === null ? null : Vite::asset($default);
    }

    /**
     * URL of the uploaded image, or null while the default is in use.
     */
    public static function uploadedImageUrl(BrandAsset $asset): ?string
    {
        return MediaStorage::url(Settings::string($asset->settingKey()));
    }

    /**
     * The chosen brand colour as #rrggbb, or the default colour.
     */
    public static function color(): string
    {
        return self::customColor() ?? self::DEFAULT_COLOR;
    }

    /**
     * The chosen brand colour, or null while the default is in use.
     */
    public static function customColor(): ?string
    {
        $color = Settings::string(self::COLOR_KEY);

        return preg_match(BrandPalette::HEX_PATTERN, $color) === 1 ? strtolower($color) : null;
    }

    /**
     * Save the brand colour. Choosing the default colour removes the override.
     */
    public static function setColor(string $color): void
    {
        $color = strtolower($color);

        if ($color === self::DEFAULT_COLOR) {
            self::resetColor();

            return;
        }

        Settings::set(self::COLOR_KEY, $color);
        Cache::forget(self::CSS_CACHE_KEY);
    }

    public static function resetColor(): void
    {
        Settings::forget(self::COLOR_KEY);
        Cache::forget(self::CSS_CACHE_KEY);
    }

    /**
     * The CSS overriding the brand variables, or an empty string while the default colour is in use.
     * The generated CSS is cached with the colour it was made from, so a colour saved any other way
     * than setColor() never serves stale CSS.
     */
    public static function css(): string
    {
        $color = self::customColor();

        if ($color === null) {
            return '';
        }

        $cached = Cache::get(self::CSS_CACHE_KEY);

        if (is_array($cached) && ($cached['color'] ?? null) === $color && is_string($cached['css'] ?? null)) {
            return $cached['css'];
        }

        $css = BrandPalette::css($color);

        Cache::forever(self::CSS_CACHE_KEY, ['color' => $color, 'css' => $css]);

        return $css;
    }
}
