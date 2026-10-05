<?php

use Filament\Support\Colors\Color;
use Modules\Platform\Branding\BrandFonts;
use Modules\Platform\Branding\BrandTokens;
use Modules\Platform\Branding\BuiltInTemplates;
use Modules\Platform\Filament\BrandPalette;

it('round-trips through arrays and normalises hex case', function () {
    $tokens = BrandTokens::fromArray([
        'primary' => '#0d735e', 'attention' => '#b7791f', 'danger' => '#b42318', 'info' => '#1d5fb8',
        'neutral' => 'stone', 'heading_font' => 'Readex Pro', 'body_font' => 'Cairo', 'radius' => 'round',
    ]);

    expect($tokens->primary)->toBe('#0D735E')
        ->and(BrandTokens::fromArray($tokens->toArray())->equals($tokens))->toBeTrue()
        ->and($tokens->radii()['xl'])->toBe('1.25rem');
});

it('refuses colours, fonts and corners outside the allowed set', function (array $change) {
    BrandTokens::fromArray(array_merge(BuiltInTemplates::tokens('noor')->toArray(), $change));
})->throws(InvalidArgumentException::class)->with([
    'not a colour' => [['primary' => 'red']],
    'css injection' => [['primary' => '#000000; background: url(x)']],
    'unknown font' => [['body_font' => 'Comic Sans MS']],
    'unknown neutral' => [['neutral' => 'pink']],
    'unknown radius' => [['radius' => 'blob']],
]);

it('measures white-text contrast as WCAG does', function () {
    expect(BrandTokens::contrastWithWhite('#000000'))->toBe(21.0)
        ->and(BrandTokens::contrastWithWhite('#0D735E'))->toBeGreaterThan(4.5)
        ->and(BrandTokens::contrastWithWhite('#F5C542'))->toBeLessThan(4.5);
});

it('ships readable built-in templates', function () {
    foreach (BuiltInTemplates::all() as $key => $template) {
        expect(BrandTokens::contrastWithWhite($template['tokens']->primary))->toBeGreaterThanOrEqual(4.5, "{$key} primary")
            ->and(BrandTokens::contrastWithWhite($template['tokens']->danger))->toBeGreaterThanOrEqual(4.5, "{$key} danger");
    }
});

it('asks Google Fonts only for weights each family has', function () {
    expect(BrandFonts::stylesheetUrl('Almarai', 'Tajawal', 'Almarai'))
        ->toBe('https://fonts.googleapis.com/css2?family=Almarai:wght@400;700&family=Tajawal:wght@400;500;700&display=swap');
});

it('keeps the academy colour itself at shade 600, where buttons use it', function (string $hex) {
    $palette = BrandPalette::from($hex);
    $back = Color::convertToHex($palette[600]);

    // Filament rounds OKLCH to three decimals, so a channel may move by one or two steps.
    $channels = fn (string $h) => array_map('hexdec', str_split(ltrim($h, '#'), 2));
    foreach (array_map(null, $channels($hex), $channels($back)) as [$want, $got]) {
        expect(abs($want - $got))->toBeLessThanOrEqual(2);
    }

    expect(array_keys($palette))->toBe([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]);
})->with(['#0D735E', '#6B3FA0', '#B42318', '#1F3A5F']);
