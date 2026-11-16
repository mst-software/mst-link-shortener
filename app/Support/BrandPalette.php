<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Builds the brand shade scale (the --color-brand-* variables of resources/css/tokens.css) from one
 * colour. The chosen colour becomes shade 600, the shade the site uses for buttons and links. Lighter
 * shades move its HSL lightness towards white, darker shades towards black; the darkest shades are
 * capped so hero sections and the footer stay dark enough for white text.
 */
class BrandPalette
{
    public const HEX_PATTERN = '/^#[0-9A-Fa-f]{6}$/';

    /**
     * Lightest lightness the tints move towards.
     */
    private const TINT_TARGET = 0.98;

    /**
     * How far each tint moves from the brand lightness towards TINT_TARGET.
     *
     * @var array<int, float>
     */
    private const TINTS = [50 => 0.95, 100 => 0.87, 200 => 0.72, 300 => 0.50, 400 => 0.28, 500 => 0.08];

    /**
     * How much the lightest tints lose saturation, so they read as soft backgrounds instead of neon.
     * It grows with the square of the tint share, which keeps the mid tints vivid.
     */
    private const TINT_DESATURATION = 0.6;

    /**
     * Each shade's lightness as a share of the brand lightness, and the most it may be.
     *
     * @var array<int, array{0: float, 1: float}>
     */
    private const SHADES = [700 => [0.80, 0.40], 800 => [0.62, 0.24], 900 => [0.46, 0.17], 950 => [0.30, 0.10]];

    /**
     * Minimum WCAG contrast for normal text.
     */
    private const MIN_TEXT_CONTRAST = 4.5;

    private const WHITE = '#ffffff';

    /**
     * Shades 50 to 950 as lowercase #rrggbb.
     *
     * @return array<int, string>
     */
    public static function shades(string $hex): array
    {
        [$hue, $saturation, $lightness] = self::toHsl($hex);

        $shades = [];
        $tintTarget = max(self::TINT_TARGET, $lightness);

        foreach (self::TINTS as $shade => $share) {
            $shades[$shade] = self::fromHsl(
                $hue,
                $saturation * (1 - self::TINT_DESATURATION * $share ** 2),
                $lightness + ($tintTarget - $lightness) * $share,
            );
        }

        $shades[600] = strtolower($hex);

        foreach (self::SHADES as $shade => [$share, $maximum]) {
            $shades[$shade] = self::fromHsl($hue, $saturation, min($lightness * $share, $maximum));
        }

        return $shades;
    }

    /**
     * Text colour for content on the brand colour: white when it is readable, otherwise the darkest
     * brand shade, whichever has the higher contrast.
     */
    public static function textColor(string $hex): string
    {
        $dark = self::shades($hex)[950];
        $whiteContrast = self::contrast($hex, self::WHITE);

        if ($whiteContrast >= self::MIN_TEXT_CONTRAST || $whiteContrast >= self::contrast($hex, $dark)) {
            return self::WHITE;
        }

        return $dark;
    }

    /**
     * The CSS that overrides the brand variables of tokens.css.
     */
    public static function css(string $hex): string
    {
        $shades = self::shades($hex);
        [$red, $green, $blue] = self::toRgb($hex);
        [$darkRed, $darkGreen, $darkBlue] = self::toRgb($shades[800]);

        $variables = ['--color-g' => $shades[600]];

        foreach ($shades as $shade => $color) {
            $variables['--color-brand-'.$shade] = $color;
        }

        $variables['--color-brand-contrast'] = self::textColor($hex);
        $variables['--shadow-button'] = "0 1px 2px rgb({$darkRed} {$darkGreen} {$darkBlue} / 0.2), 0 6px 16px -6px rgb({$red} {$green} {$blue} / 0.55)";
        $variables['--shadow-card-hover'] = "0 2px 4px rgb(15 31 21 / 0.05), 0 18px 40px -12px rgb({$darkRed} {$darkGreen} {$darkBlue} / 0.22)";

        $declarations = '';

        foreach ($variables as $name => $value) {
            $declarations .= $name.':'.$value.';';
        }

        return ':root{'.$declarations.'}';
    }

    /**
     * WCAG contrast ratio of two colours (1 to 21).
     */
    public static function contrast(string $first, string $second): float
    {
        $lighter = max(self::luminance($first), self::luminance($second));
        $darker = min(self::luminance($first), self::luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * WCAG relative luminance (0 for black, 1 for white).
     */
    public static function luminance(string $hex): float
    {
        $channels = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, self::toRgb($hex));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function toRgb(string $hex): array
    {
        if (preg_match(self::HEX_PATTERN, $hex) !== 1) {
            throw new InvalidArgumentException('Expected a colour such as #038018.');
        }

        return [(int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2))];
    }

    /**
     * @return array{0: float, 1: float, 2: float} Hue in degrees, saturation and lightness from 0 to 1.
     */
    private static function toHsl(string $hex): array
    {
        [$red, $green, $blue] = array_map(fn (int $channel): float => $channel / 255, self::toRgb($hex));

        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $lightness = ($max + $min) / 2;
        $delta = $max - $min;

        if ($delta == 0.0) {
            return [0.0, 0.0, $lightness];
        }

        $saturation = $delta / (1 - abs(2 * $lightness - 1));

        $hue = match ($max) {
            $red => fmod(($green - $blue) / $delta + 6, 6),
            $green => ($blue - $red) / $delta + 2,
            default => ($red - $green) / $delta + 4,
        };

        return [$hue * 60, $saturation, $lightness];
    }

    private static function fromHsl(float $hue, float $saturation, float $lightness): string
    {
        $saturation = max(0.0, min(1.0, $saturation));
        $lightness = max(0.0, min(1.0, $lightness));

        $chroma = (1 - abs(2 * $lightness - 1)) * $saturation;
        $section = $hue / 60;
        $second = $chroma * (1 - abs(fmod($section, 2) - 1));
        $match = $lightness - $chroma / 2;

        [$red, $green, $blue] = match (true) {
            $section < 1 => [$chroma, $second, 0.0],
            $section < 2 => [$second, $chroma, 0.0],
            $section < 3 => [0.0, $chroma, $second],
            $section < 4 => [0.0, $second, $chroma],
            $section < 5 => [$second, 0.0, $chroma],
            default => [$chroma, 0.0, $second],
        };

        return sprintf(
            '#%02x%02x%02x',
            (int) round(($red + $match) * 255),
            (int) round(($green + $match) * 255),
            (int) round(($blue + $match) * 255),
        );
    }
}
