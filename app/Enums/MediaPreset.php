<?php

namespace App\Enums;

/**
 * How an uploaded image is processed by App\Services\MediaUploader: where it is stored, how far it is
 * scaled down and which format it is saved in.
 */
enum MediaPreset: string
{
    case Logo = 'logo';
    case Favicon = 'favicon';
    case ShareImage = 'share_image';
    case CampaignImage = 'campaign_image';

    /**
     * Folder on the upload disk.
     */
    public function directory(): string
    {
        return match ($this) {
            self::Logo, self::Favicon, self::ShareImage => 'branding',
            self::CampaignImage => 'campaigns',
        };
    }

    /**
     * Longest side in pixels after scaling down, or null to keep the uploaded size (campaign instruction
     * images are screenshots that must stay readable).
     */
    public function maxDimension(): ?int
    {
        return match ($this) {
            self::Logo => 1000,
            self::Favicon => 256,
            self::ShareImage => 1200,
            self::CampaignImage => null,
        };
    }

    /**
     * Favicons are saved as PNG because older Safari versions do not show WebP favicons; everything else
     * is saved as WebP.
     */
    public function savesAsPng(): bool
    {
        return $this === self::Favicon;
    }
}
