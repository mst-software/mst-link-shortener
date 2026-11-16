<?php

namespace App\Enums;

/**
 * The brand images operators can replace on the admin Appearance settings page.
 */
enum BrandAsset: string
{
    case LogoLight = 'logo_light';
    case LogoDark = 'logo_dark';
    case Favicon = 'favicon';
    case ShareImage = 'share_image';

    public function label(): string
    {
        return match ($this) {
            self::LogoLight => 'Logo for light backgrounds',
            self::LogoDark => 'Logo for dark and coloured backgrounds',
            self::Favicon => 'Favicon',
            self::ShareImage => 'Social share image',
        };
    }

    public function help(): string
    {
        return match ($this) {
            self::LogoLight => 'A dark logo, shown on white backgrounds such as the sign in and sign up pages. A wide image with a transparent background works best.',
            self::LogoDark => 'A white or light logo, shown on the coloured header and footer of the public pages and in the member and advertiser areas. Use a transparent background.',
            self::Favicon => 'The small icon in the browser tab. Use a square image of at least 64 by 64 pixels; it is saved as a 256 pixel PNG at most.',
            self::ShareImage => 'Optional. Shown when a page of the site is shared on social networks or in chat apps. 1200 by 630 pixels works best.',
        };
    }

    /**
     * The settings key holding the stored media reference.
     */
    public function settingKey(): string
    {
        return 'brand_'.$this->value;
    }

    public function preset(): MediaPreset
    {
        return match ($this) {
            self::LogoLight, self::LogoDark => MediaPreset::Logo,
            self::Favicon => MediaPreset::Favicon,
            self::ShareImage => MediaPreset::ShareImage,
        };
    }

    /**
     * The bundled image used while nothing is uploaded, or null when the image is optional.
     */
    public function defaultImage(): ?string
    {
        return match ($this) {
            self::LogoLight => 'resources/images/logo-poppins-dark.png',
            self::LogoDark => 'resources/images/logo-poppins-white.png',
            self::Favicon => 'resources/images/favicon.png',
            self::ShareImage => null,
        };
    }
}
