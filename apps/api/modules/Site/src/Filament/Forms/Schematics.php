<?php

namespace Modules\Site\Filament\Forms;

/**
 * Small drawings of each layout for the builder's pickers: a variant's shape
 * (text lines, a picture, cards…) in 64×40, in the panel's own colours
 * (currentColor for lines, the primary colour for the accent). Fixed markup,
 * never built from what anyone typed.
 */
final class Schematics
{
    private const LINE = 'fill="currentColor" opacity=".55"';

    private const SOFT = 'fill="currentColor" opacity=".16"';

    private const ACCENT = 'fill="var(--primary-500)"';

    public static function svg(string $type, string $variant): string
    {
        return '<svg viewBox="0 0 64 40" width="64" height="40" aria-hidden="true" focusable="false">'.self::shape($type, $variant).'</svg>';
    }

    private static function lines(float $x, float $y, array $widths, float $h = 2.4, float $gap = 4.4, string $fill = self::LINE): string
    {
        $out = '';
        foreach ($widths as $i => $w) {
            $out .= '<rect x="'.$x.'" y="'.($y + $i * $gap).'" width="'.$w.'" height="'.$h.'" rx="1.2" '.$fill.'/>';
        }

        return $out;
    }

    private static function box(float $x, float $y, float $w, float $h, string $fill = self::SOFT, float $r = 2.5): string
    {
        return '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" rx="'.$r.'" '.$fill.'/>';
    }

    private static function button(float $x, float $y, float $w = 12): string
    {
        return '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="4.5" rx="2.25" '.self::ACCENT.'/>';
    }

    private static function shape(string $type, string $variant): string
    {
        return match ($type.'.'.$variant) {
            'hero.centered', 'cta.card' => self::box(6, 4, 52, 32, self::SOFT, 3).self::lines(16, 11, [32, 22], 3).self::button(26, 25),
            'hero.split', 'cta.split', 'image_text.side', 'video.side' => self::lines(5, 10, [24, 18, 20], 3).self::button(5, 26).self::box(35, 6, 24, 28),
            'hero.video', 'cta.banner' => '<rect x="2" y="3" width="60" height="34" rx="3" fill="currentColor" opacity=".75"/>'.self::lines(14, 13, [36, 26], 3, 5, 'fill="white" opacity=".9"').self::button(26, 26),
            'hero.phones', 'app.phones' => self::lines(5, 12, [22, 16], 3).self::button(5, 24).'<rect x="34" y="5" width="11" height="30" rx="3" fill="currentColor" opacity=".7"/><rect x="47" y="9" width="11" height="26" rx="3" '.self::ACCENT.' opacity=".85"/>',
            'hero.minimal' => self::lines(6, 14, [40, 28], 3.4, 7),
            'features.cards', 'subjects.cards', 'teachers.cards', 'pricing.cards', 'steps.cards', 'stats.cards', 'contact.cards' => self::box(3, 9, 18, 24).self::box(23, 9, 18, 24).self::box(43, 9, 18, 24).self::lines(6, 14, [10, 12], 2.2).self::lines(26, 14, [10, 12], 2.2).self::lines(46, 14, [10, 12], 2.2),
            'features.icons' => '<circle cx="12" cy="13" r="4" '.self::ACCENT.'/><circle cx="32" cy="13" r="4" '.self::ACCENT.'/><circle cx="52" cy="13" r="4" '.self::ACCENT.'/>'.self::lines(5, 22, [14, 10]).self::lines(25, 22, [14, 10]).self::lines(45, 22, [14, 10]),
            'features.list', 'subjects.list' => self::lines(10, 8, [20, 20, 20, 20], 2.4, 7).self::lines(40, 8, [18, 18, 18, 18], 2.4, 7).'<circle cx="6" cy="9.2" r="1.6" '.self::ACCENT.'/><circle cx="6" cy="16.2" r="1.6" '.self::ACCENT.'/><circle cx="36" cy="9.2" r="1.6" '.self::ACCENT.'/><circle cx="36" cy="16.2" r="1.6" '.self::ACCENT.'/>',
            'features.numbers' => '<text x="5" y="22" font-size="14" font-weight="700" fill="var(--primary-500)">1</text><text x="26" y="22" font-size="14" font-weight="700" fill="var(--primary-500)">2</text><text x="47" y="22" font-size="14" font-weight="700" fill="var(--primary-500)">3</text>'.self::lines(5, 27, [14, 10]).self::lines(26, 27, [14, 10]).self::lines(47, 27, [14, 10]),
            'subjects.chips' => self::box(4, 12, 16, 6, self::SOFT, 3).self::box(22, 12, 20, 6, self::SOFT, 3).self::box(44, 12, 15, 6, self::SOFT, 3).self::box(10, 21, 18, 6, self::SOFT, 3).self::box(30, 21, 22, 6, self::SOFT, 3),
            'teachers.portraits' => self::box(4, 4, 17, 22).self::box(23.5, 4, 17, 22).self::box(43, 4, 17, 22).self::lines(4, 29, [12, 8]).self::lines(23.5, 29, [12, 8]).self::lines(43, 29, [12, 8]),
            'teachers.compact' => '<circle cx="9" cy="12" r="4" fill="currentColor" opacity=".3"/><circle cx="9" cy="27" r="4" fill="currentColor" opacity=".3"/><circle cx="39" cy="12" r="4" fill="currentColor" opacity=".3"/><circle cx="39" cy="27" r="4" fill="currentColor" opacity=".3"/>'.self::lines(15, 10, [14, 9]).self::lines(15, 25, [14, 9]).self::lines(45, 10, [14, 9]).self::lines(45, 25, [14, 9]),
            'pricing.table' => self::box(4, 5, 56, 30, self::SOFT, 2).'<path d="M4 13h56M4 21h56M4 29h56M22 5v30M41 5v30" stroke="currentColor" stroke-opacity=".35" stroke-width=".8" fill="none"/>'.self::box(41, 5, 19, 30, self::ACCENT.' opacity=".25"', 0),
            'steps.line' => '<path d="M10 14H54" stroke="currentColor" stroke-opacity=".4" stroke-width="1.4"/><circle cx="10" cy="14" r="4" '.self::ACCENT.'/><circle cx="32" cy="14" r="4" '.self::ACCENT.'/><circle cx="54" cy="14" r="4" '.self::ACCENT.'/>'.self::lines(3, 24, [14, 10]).self::lines(25, 24, [14, 10]).self::lines(47, 24, [14, 10]),
            'app.image' => self::lines(5, 12, [22, 16], 3).self::button(5, 24).self::box(34, 5, 26, 30),
            'app.plain' => self::lines(14, 10, [36, 26], 3).self::button(17, 25, 13).self::button(33, 25, 13),
            'testimonials.marquee', 'logos.marquee' => self::box(-6, 10, 20, 20).self::box(16, 10, 20, 20).self::box(38, 10, 20, 20).self::box(60, 10, 20, 20),
            'testimonials.grid', 'gallery.grid', 'logos.grid' => self::box(4, 4, 27, 15).self::box(33, 4, 27, 15).self::box(4, 21, 27, 15).self::box(33, 21, 27, 15),
            'testimonials.single' => '<text x="8" y="17" font-size="16" font-weight="700" fill="var(--primary-500)">”</text>'.self::lines(14, 12, [40, 36, 26], 2.6, 5).'<circle cx="20" cy="33" r="2" fill="currentColor" opacity=".5"/>',
            'faq.accordion' => self::box(6, 5, 52, 7, self::SOFT, 2).self::box(6, 14, 52, 7, self::SOFT, 2).self::box(6, 23, 52, 7, self::SOFT, 2).self::lines(9, 7.5, [24, 0]).self::lines(9, 16.5, [30, 0]).self::lines(9, 25.5, [20, 0]),
            'faq.two_columns', 'rich_text.two_columns' => self::lines(4, 8, [18, 12], 3.2, 6).self::box(26, 5, 34, 7, self::SOFT, 2).self::box(26, 14, 34, 7, self::SOFT, 2).self::box(26, 23, 34, 7, self::SOFT, 2),
            'stats.band', 'contact.band' => self::box(0, 9, 64, 22, self::ACCENT.' opacity=".22"', 0).'<text x="5" y="22" font-size="9" font-weight="700" fill="currentColor">12</text><text x="26" y="22" font-size="9" font-weight="700" fill="currentColor">98</text><text x="47" y="22" font-size="9" font-weight="700" fill="currentColor">4+</text>'.self::lines(5, 25, [12, 0]).self::lines(26, 25, [12, 0]).self::lines(47, 25, [12, 0]),
            'rich_text.article' => self::lines(14, 6, [36, 34, 36, 30, 36, 22], 2.4, 5),
            'image_text.overlap' => self::box(4, 4, 38, 32).'<rect x="30" y="12" width="30" height="20" rx="2.5" fill="white" stroke="currentColor" stroke-opacity=".2"/>'.self::lines(34, 17, [20, 16, 18]),
            'image_text.stacked' => self::box(10, 3, 44, 20).self::lines(14, 27, [36, 28]),
            'gallery.masonry' => self::box(4, 4, 18, 20).self::box(4, 26, 18, 10).self::box(24, 4, 18, 12).self::box(24, 18, 18, 18).self::box(44, 4, 16, 32),
            'gallery.carousel' => self::box(8, 8, 22, 24).self::box(33, 8, 22, 24).self::box(58, 8, 22, 24).'<circle cx="5" cy="20" r="3" fill="currentColor" opacity=".55"/>',
            'video.framed' => self::box(10, 4, 44, 26).'<path d="M29 12l8 5-8 5z" '.self::ACCENT.'/>'.self::lines(22, 34, [20, 0]),
            'video.wide' => self::box(0, 6, 64, 28, self::SOFT, 0).'<path d="M29 15l8 5-8 5z" '.self::ACCENT.'/>',
            'logos.row' => self::box(3, 15, 11, 10, self::SOFT, 1.5).self::box(16, 15, 11, 10, self::SOFT, 1.5).self::box(29, 15, 11, 10, self::SOFT, 1.5).self::box(42, 15, 11, 10, self::SOFT, 1.5).self::box(55, 15, 8, 10, self::SOFT, 1.5),
            'form.split' => self::lines(4, 10, [22, 16, 18], 3, 5).'<rect x="32" y="4" width="28" height="32" rx="3" fill="white" stroke="currentColor" stroke-opacity=".25"/>'.self::box(35, 9, 22, 4, self::SOFT, 1).self::box(35, 16, 22, 4, self::SOFT, 1).self::button(35, 26, 22),
            'form.centered' => self::lines(20, 4, [24, 0], 3).'<rect x="14" y="10" width="36" height="27" rx="3" fill="white" stroke="currentColor" stroke-opacity=".25"/>'.self::box(18, 14, 28, 4, self::SOFT, 1).self::box(18, 21, 28, 4, self::SOFT, 1).self::button(18, 29, 28),
            'cards.grid' => self::box(3, 4, 18, 32, self::SOFT, 2.5).self::box(23, 4, 18, 32, self::SOFT, 2.5).self::box(43, 4, 18, 32, self::SOFT, 2.5)
                .self::box(3, 4, 18, 13, 'fill="currentColor" opacity=".3"', 2.5).self::box(23, 4, 18, 13, 'fill="currentColor" opacity=".3"', 2.5).self::box(43, 4, 18, 13, 'fill="currentColor" opacity=".3"', 2.5)
                .self::lines(5, 21, [12, 9]).self::lines(25, 21, [12, 9]).self::lines(45, 21, [12, 9]).self::button(5, 30, 8).self::button(25, 30, 8).self::button(45, 30, 8),
            'cards.list' => self::box(3, 4, 58, 9, self::SOFT, 2).self::box(3, 15.5, 58, 9, self::SOFT, 2).self::box(3, 27, 58, 9, self::SOFT, 2)
                .self::box(3, 4, 13, 9, 'fill="currentColor" opacity=".3"', 2).self::box(3, 15.5, 13, 9, 'fill="currentColor" opacity=".3"', 2).self::box(3, 27, 13, 9, 'fill="currentColor" opacity=".3"', 2)
                .self::lines(19, 6.5, [22, 0]).self::lines(19, 18, [26, 0]).self::lines(19, 29.5, [18, 0]).self::button(50, 6.5, 8).self::button(50, 18, 8).self::button(50, 29.5, 8),
            'cards.overlay' => '<rect x="3" y="4" width="18" height="32" rx="2.5" fill="currentColor" opacity=".7"/><rect x="23" y="4" width="18" height="32" rx="2.5" fill="currentColor" opacity=".7"/><rect x="43" y="4" width="18" height="32" rx="2.5" fill="currentColor" opacity=".7"/>'
                .self::lines(5, 25, [12, 8], 2.4, 4.4, 'fill="white" opacity=".9"').self::lines(25, 25, [12, 8], 2.4, 4.4, 'fill="white" opacity=".9"').self::lines(45, 25, [12, 8], 2.4, 4.4, 'fill="white" opacity=".9"'),
            'spacer.space' => '<path d="M32 8v24M28 12l4-4 4 4M28 28l4 4 4-4" stroke="currentColor" stroke-opacity=".5" stroke-width="1.4" fill="none"/>',
            'spacer.divider' => '<path d="M6 20h52" stroke="currentColor" stroke-opacity=".5" stroke-width="1.4"/>',
            default => self::lines(8, 12, [40, 30, 36], 3, 6),
        };
    }
}
