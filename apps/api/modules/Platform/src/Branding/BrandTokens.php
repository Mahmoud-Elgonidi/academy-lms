<?php

namespace Modules\Platform\Branding;

use InvalidArgumentException;

/**
 * The look of one academy: colours, fonts and corner style. Stored as JSON on
 * academy_branding and on saved templates; read by the back office, the web
 * app and the mobile app (GET /api/v1/branding).
 */
final readonly class BrandTokens
{
    public const NEUTRALS = ['slate', 'gray', 'zinc', 'neutral', 'stone'];

    public const RADII = ['sharp', 'soft', 'round'];

    public const COLOURS = ['primary', 'attention', 'danger', 'info'];

    public function __construct(
        public string $primary,
        public string $attention,
        public string $danger,
        public string $info,
        public string $neutral,
        public string $headingFont,
        public string $bodyFont,
        public string $radius,
    ) {
        foreach (self::COLOURS as $colour) {
            if (! self::isHex($this->{$colour})) {
                throw new InvalidArgumentException("[{$colour}] must be a #rrggbb colour.");
            }
        }
        if (! in_array($neutral, self::NEUTRALS, true)) {
            throw new InvalidArgumentException("Unknown neutral [{$neutral}].");
        }
        if (! BrandFonts::has($headingFont) || ! BrandFonts::has($bodyFont)) {
            throw new InvalidArgumentException('Fonts must come from BrandFonts.');
        }
        if (! in_array($radius, self::RADII, true)) {
            throw new InvalidArgumentException("Unknown radius [{$radius}].");
        }
    }

    /** @param  array<string, mixed>  $tokens */
    public static function fromArray(array $tokens): self
    {
        return new self(
            primary: strtoupper((string) ($tokens['primary'] ?? '')),
            attention: strtoupper((string) ($tokens['attention'] ?? '')),
            danger: strtoupper((string) ($tokens['danger'] ?? '')),
            info: strtoupper((string) ($tokens['info'] ?? '')),
            neutral: (string) ($tokens['neutral'] ?? ''),
            headingFont: (string) ($tokens['heading_font'] ?? ''),
            bodyFont: (string) ($tokens['body_font'] ?? ''),
            radius: (string) ($tokens['radius'] ?? ''),
        );
    }

    /** @return array{primary: string, attention: string, danger: string, info: string, neutral: string, heading_font: string, body_font: string, radius: string} */
    public function toArray(): array
    {
        return [
            'primary' => $this->primary,
            'attention' => $this->attention,
            'danger' => $this->danger,
            'info' => $this->info,
            'neutral' => $this->neutral,
            'heading_font' => $this->headingFont,
            'body_font' => $this->bodyFont,
            'radius' => $this->radius,
        ];
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    /** WCAG contrast of white text on this colour (buttons, badges). 4.5 is the floor for body text. */
    public static function contrastWithWhite(string $hex): float
    {
        return round(1.05 / (self::luminance($hex) + 0.05), 2);
    }

    public static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
    }

    /** Corner radii in rem for small, medium, large and extra-large elements. */
    public function radii(): array
    {
        return match ($this->radius) {
            'sharp' => ['sm' => '0.125rem', 'md' => '0.25rem', 'lg' => '0.3125rem', 'xl' => '0.5rem'],
            'round' => ['sm' => '0.375rem', 'md' => '0.625rem', 'lg' => '0.875rem', 'xl' => '1.25rem'],
            default => ['sm' => '0.25rem', 'md' => '0.375rem', 'lg' => '0.5rem', 'xl' => '0.75rem'],
        };
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(
            function (string $pair): float {
                $c = hexdec($pair) / 255;

                return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            },
            str_split(substr($hex, 1), 2),
        );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
