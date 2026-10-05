<?php

namespace Modules\Platform\Branding;

/**
 * Ready-made looks every academy can start from. An academy cannot change
 * these; it edits a copy and saves it as its own template.
 */
final class BuiltInTemplates
{
    public const DEFAULT = 'noor';

    /** @return array<string, array{name_ar: string, name_en: string, tokens: BrandTokens}> */
    public static function all(): array
    {
        return [
            'noor' => [
                'name_ar' => 'النور', 'name_en' => 'Noor',
                'tokens' => new BrandTokens('#0D735E', '#B7791F', '#B42318', '#1D5FB8', 'stone', 'Readex Pro', 'IBM Plex Sans Arabic', 'soft'),
            ],
            'sky' => [
                'name_ar' => 'السماء', 'name_en' => 'Sky',
                'tokens' => new BrandTokens('#1D5FB8', '#B7791F', '#B42318', '#0E7490', 'slate', 'Noto Kufi Arabic', 'Noto Sans Arabic', 'soft'),
            ],
            'violet' => [
                'name_ar' => 'البنفسج', 'name_en' => 'Violet',
                'tokens' => new BrandTokens('#6B3FA0', '#B7791F', '#B42318', '#1D5FB8', 'zinc', 'El Messiri', 'Cairo', 'round'),
            ],
            'sand' => [
                'name_ar' => 'الرمال', 'name_en' => 'Sand',
                'tokens' => new BrandTokens('#7C4A12', '#B7791F', '#B42318', '#1D5FB8', 'stone', 'Changa', 'Almarai', 'sharp'),
            ],
            'ink' => [
                'name_ar' => 'الحبر', 'name_en' => 'Ink',
                'tokens' => new BrandTokens('#1F3A5F', '#B7791F', '#B42318', '#0E7490', 'slate', 'Tajawal', 'Tajawal', 'sharp'),
            ],
        ];
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function tokens(string $key): BrandTokens
    {
        return self::all()[$key]['tokens'] ?? self::all()[self::DEFAULT]['tokens'];
    }
}
