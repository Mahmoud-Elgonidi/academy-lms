<?php

namespace Modules\Platform\Site;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * What the platform's page says, as the console sets it: the opening words,
 * a few numbers, what academies said, questions and answers, which sections
 * show, how to reach the team, the demo's address and the words search
 * engines read. Plain words only (the page writes them escaped), in Arabic
 * and English, each held to its length. Until an owner saves it, the page
 * says the defaults (platform::site.defaults).
 */
final class SiteContent
{
    /** The page's sections the console can hide (the opening and the request form always show). */
    public const SECTIONS = ['stats', 'audiences', 'features', 'app', 'steps', 'pricing', 'testimonials', 'faq'];

    public const MAX_STATS = 4;

    public const MAX_TESTIMONIALS = 6;

    public const MAX_FAQS = 10;

    private const LOCALES = ['ar', 'en'];

    /**
     * The content to keep, cleaned and checked.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function normalize(array $input): array
    {
        $content = [
            'hero' => self::pairs($input['hero'] ?? [], ['title', 'lead']),
            'stats' => self::rows($input['stats'] ?? [], fn (array $s) => ['value' => self::text($s['value'] ?? '')] + self::pairs($s, ['label'])),
            'testimonials' => self::rows($input['testimonials'] ?? [], fn (array $t) => ['name' => self::text($t['name'] ?? '')] + self::pairs($t, ['role', 'quote'])),
            'faqs' => self::rows($input['faqs'] ?? [], fn (array $f) => self::pairs($f, ['q', 'a'])),
            'sections' => array_values(array_intersect(self::SECTIONS, array_map('strval', (array) ($input['sections'] ?? [])))),
            'contact' => [
                'whatsapp' => self::phone($input['contact']['whatsapp'] ?? ''),
                'email' => self::text($input['contact']['email'] ?? ''),
            ],
            'demo_url' => self::text($input['demo_url'] ?? ''),
            'seo' => self::pairs($input['seo'] ?? [], ['title', 'description']),
        ];

        $text = fn (int $max) => ['nullable', 'string', 'max:'.$max];
        $both = fn (string $path, int $max, bool $required = false) => collect(self::LOCALES)
            ->mapWithKeys(fn (string $l) => ["{$path}_{$l}" => $required ? ['required', 'string', 'max:'.$max] : $text($max)])->all();
        Validator::make($content, [
            ...$both('hero.title', 120, true),
            ...$both('hero.lead', 300, true),
            'stats' => ['array', 'max:'.self::MAX_STATS],
            'stats.*.value' => ['required', 'string', 'max:12'],
            ...$both('stats.*.label', 40, true),
            'testimonials' => ['array', 'max:'.self::MAX_TESTIMONIALS],
            'testimonials.*.name' => ['required', 'string', 'max:60'],
            ...$both('testimonials.*.role', 60),
            ...$both('testimonials.*.quote', 400, true),
            'faqs' => ['array', 'max:'.self::MAX_FAQS],
            ...$both('faqs.*.q', 160, true),
            ...$both('faqs.*.a', 800, true),
            'contact.whatsapp' => ['nullable', 'regex:/^\+[1-9]\d{6,14}$/'],
            'contact.email' => ['nullable', 'email:rfc', 'max:190'],
            'demo_url' => ['nullable', 'url:https,http', 'max:300'],
            ...$both('seo.title', 70),
            ...$both('seo.description', 170),
        ], [], self::attributes())->validate();

        return $content;
    }

    /** @return array<string, mixed> what the page says before an owner saves anything */
    public static function defaults(): array
    {
        return [
            'hero' => self::said('hero', ['title', 'lead']),
            'stats' => array_map(fn (int $i): array => ['value' => (string) __("platform::site.defaults.stats.{$i}.value", [], 'en')] + self::said("stats.{$i}", ['label']), range(0, 3)),
            'testimonials' => [],
            'faqs' => array_map(fn (int $i): array => self::said("faqs.{$i}", ['q', 'a']), range(0, 5)),
            'sections' => array_values(array_diff(self::SECTIONS, ['testimonials'])),
            'contact' => ['whatsapp' => '', 'email' => ''],
            'demo_url' => '',
            'seo' => self::said('seo', ['title', 'description']),
        ];
    }

    /**
     * A default's words in both languages, as the content keeps them ("title_ar", "title_en").
     *
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private static function said(string $prefix, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            foreach (self::LOCALES as $locale) {
                $out["{$key}_{$locale}"] = (string) __("platform::site.defaults.{$prefix}.{$key}", [], $locale);
            }
        }

        return $out;
    }

    /**
     * The content in one language, for the page.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function localized(array $content, string $locale): array
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'ar';
        $rows = function (string $list, array $keys, ?string $own = null) use ($content, $locale): array {
            $out = [];
            foreach ((array) ($content[$list] ?? []) as $row) {
                $out[] = ($own !== null ? [$own => (string) ($row[$own] ?? '')] : []) + self::pick((array) $row, $keys, $locale);
            }

            return $out;
        };

        return [
            'hero' => self::pick((array) ($content['hero'] ?? []), ['title', 'lead'], $locale),
            'stats' => $rows('stats', ['label'], 'value'),
            'testimonials' => $rows('testimonials', ['role', 'quote'], 'name'),
            'faqs' => $rows('faqs', ['q', 'a']),
            'sections' => array_values((array) ($content['sections'] ?? [])),
            'contact' => ['whatsapp' => ($content['contact']['whatsapp'] ?? '') ?: null, 'email' => ($content['contact']['email'] ?? '') ?: null],
            'demo_url' => ($content['demo_url'] ?? '') ?: null,
            'seo' => self::pick((array) ($content['seo'] ?? []), ['title', 'description'], $locale),
        ];
    }

    /**
     * A row's words in one language, the other's where it is empty.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private static function pick(array $row, array $keys, string $locale): array
    {
        $other = $locale === 'ar' ? 'en' : 'ar';
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = (string) (($row["{$key}_{$locale}"] ?? '') ?: ($row["{$key}_{$other}"] ?? ''));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private static function pairs(mixed $row, array $keys): array
    {
        $row = is_array($row) ? $row : [];

        return collect($keys)->flatMap(fn (string $k) => collect(self::LOCALES)->mapWithKeys(fn (string $l) => ["{$k}_{$l}" => self::text($row["{$k}_{$l}"] ?? '')]))->all();
    }

    /** @return list<array<string, string>> rows with anything in them */
    private static function rows(mixed $rows, callable $shape): array
    {
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row)) {
                $clean = $shape($row);
                if (implode('', $clean) !== '') {
                    $out[] = $clean;
                }
            }
        }

        return $out;
    }

    /** One line or a few, without what cannot be read (controls, surrounding spaces). */
    private static function text(mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : '';
        $text = (string) preg_replace('/[^\P{C}\n]/u', '', str_replace(["\r\n", "\r"], "\n", $text));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    private static function phone(mixed $value): string
    {
        return (string) preg_replace('/[\s\-().]/', '', self::text($value));
    }

    /** @return array<string, string> */
    private static function attributes(): array
    {
        $l = 'platform::site.fields.';

        return [
            'hero.title_ar' => __($l.'title_ar'), 'hero.title_en' => __($l.'title_en'),
            'hero.lead_ar' => __($l.'lead_ar'), 'hero.lead_en' => __($l.'lead_en'),
            'contact.whatsapp' => __($l.'whatsapp'), 'contact.email' => __($l.'email'), 'demo_url' => __($l.'demo_url'),
        ];
    }
}
