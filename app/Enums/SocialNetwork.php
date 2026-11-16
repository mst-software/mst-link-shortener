<?php

namespace App\Enums;

use App\Services\Settings;

/**
 * Social and contact links an operator can show in the public footer.
 */
enum SocialNetwork: string
{
    case Facebook = 'facebook';
    case Telegram = 'telegram';
    case YouTube = 'youtube';
    case TikTok = 'tiktok';
    case Discord = 'discord';
    case X = 'x';

    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Telegram => 'Telegram',
            self::YouTube => 'YouTube',
            self::TikTok => 'TikTok',
            self::Discord => 'Discord',
            self::X => 'X (Twitter)',
        };
    }

    /**
     * Key of the setting holding the link, for example "social_facebook".
     */
    public function settingKey(): string
    {
        return 'social_'.$this->value;
    }

    /**
     * Example link shown as a placeholder in the admin form.
     */
    public function example(): string
    {
        return match ($this) {
            self::Facebook => 'https://www.facebook.com/yourpage',
            self::Telegram => 'https://t.me/yourchannel',
            self::YouTube => 'https://www.youtube.com/@yourchannel',
            self::TikTok => 'https://www.tiktok.com/@youraccount',
            self::Discord => 'https://discord.gg/yourinvite',
            self::X => 'https://x.com/youraccount',
        };
    }

    /**
     * The filled links, keyed by label.
     *
     * @return array<string, string>
     */
    public static function filledLinks(): array
    {
        $links = [];

        foreach (self::cases() as $network) {
            $url = Settings::string($network->settingKey());

            if ($url !== '') {
                $links[$network->label()] = $url;
            }
        }

        return $links;
    }
}
