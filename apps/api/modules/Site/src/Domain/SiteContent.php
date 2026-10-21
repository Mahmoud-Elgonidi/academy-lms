<?php

namespace Modules\Site\Domain;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * What an academy's public page says, as its owner or admins set it: the
 * opening words, why families choose it, what families said (real words,
 * with their consent: never made up, so none by default), questions and
 * answers, which sections show, its WhatsApp number and the words search
 * engines read. Plain words only (the page writes them escaped), in Arabic
 * and English: one of the two is enough, the page reads the other where one
 * is empty. Each held to its length. Until the academy saves, the page says
 * the defaults (site::site.defaults), which name no academy: the page puts
 * the academy's own name around them.
 *
 * Shaped with plain loops: the public page's controller reads it, and
 * Scramble's inference of collection closures breaks other endpoints.
 */
final class SiteContent
{
    /** The page's sections the academy can hide (the opening and the free-trial form always show). */
    public const SECTIONS = ['subjects', 'teachers', 'pricing', 'app', 'steps', 'testimonials', 'faq', 'teach'];

    public const MAX_FEATURES = 6;

    public const MAX_TESTIMONIALS = 6;

    public const MAX_FAQS = 10;

    /** How many of each the defaults carry (site::site.defaults). */
    private const DEFAULT_FEATURES = 6;

    private const DEFAULT_FAQS = 6;

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
            'features' => self::rows($input['features'] ?? [], ['title', 'text'], null),
            'testimonials' => self::rows($input['testimonials'] ?? [], ['role', 'quote'], 'name'),
            'faqs' => self::rows($input['faqs'] ?? [], ['q', 'a'], null),
            'sections' => self::sections($input['sections'] ?? []),
            'whatsapp' => self::phone($input['whatsapp'] ?? ''),
            'seo' => self::pairs($input['seo'] ?? [], ['title', 'description']),
        ];

        $rules = [
            'features' => ['array', 'max:'.self::MAX_FEATURES],
            'testimonials' => ['array', 'max:'.self::MAX_TESTIMONIALS],
            'testimonials.*.name' => ['required', 'string', 'max:60'],
            'faqs' => ['array', 'max:'.self::MAX_FAQS],
            'whatsapp' => ['nullable', 'regex:/^\+[1-9]\d{6,14}$/'],
        ];
        foreach ([
            ['hero.title', 120, true], ['hero.lead', 300, true],
            ['features.*.title', 60, true], ['features.*.text', 200, true],
            ['testimonials.*.role', 60, false], ['testimonials.*.quote', 400, true],
            ['faqs.*.q', 160, true], ['faqs.*.a', 800, true],
            ['seo.title', 70, false], ['seo.description', 170, false],
        ] as [$path, $max, $needed]) {
            // One language is enough: the page reads the other where one is empty.
            $rules["{$path}_ar"] = [$needed ? "required_without:{$path}_en" : 'nullable', 'nullable', 'string', 'max:'.$max];
            $rules["{$path}_en"] = [$needed ? "required_without:{$path}_ar" : 'nullable', 'nullable', 'string', 'max:'.$max];
        }
        Validator::make($content, $rules, [], self::attributes())->validate();

        return $content;
    }

    /** @return array<string, mixed> what the page says before the academy saves anything */
    public static function defaults(): array
    {
        $features = [];
        for ($i = 0; $i < self::DEFAULT_FEATURES; $i++) {
            $features[] = self::said("features.{$i}", ['title', 'text']);
        }
        $faqs = [];
        for ($i = 0; $i < self::DEFAULT_FAQS; $i++) {
            $faqs[] = self::said("faqs.{$i}", ['q', 'a']);
        }

        return [
            'hero' => self::said('hero', ['title', 'lead']),
            'features' => $features,
            'testimonials' => [],
            'faqs' => $faqs,
            // What families said shows once the academy has real words to put there.
            'sections' => array_values(array_diff(self::SECTIONS, ['testimonials'])),
            'whatsapp' => '',
            'seo' => self::said('seo', ['title', 'description']),
        ];
    }

    /**
     * The content in one language, for the page: each text in that
     * language, the other's where it is empty.
     *
     * @param  array<string, mixed>  $content
     * @return array{hero: array{title: string, lead: string}, features: list<array{title: string, text: string}>, testimonials: list<array{name: string, role: string, quote: string}>, faqs: list<array{q: string, a: string}>, sections: list<string>, whatsapp: ?string, seo: array{title: string, description: string}}
     */
    public static function localized(array $content, string $locale): array
    {
        $locale = in_array($locale, self::LOCALES, true) ? $locale : 'ar';

        $features = [];
        foreach (self::listOf($content['features'] ?? null) as $row) {
            $features[] = ['title' => self::word($row, 'title', $locale), 'text' => self::word($row, 'text', $locale)];
        }
        $testimonials = [];
        foreach (self::listOf($content['testimonials'] ?? null) as $row) {
            $testimonials[] = ['name' => (string) ($row['name'] ?? ''), 'role' => self::word($row, 'role', $locale), 'quote' => self::word($row, 'quote', $locale)];
        }
        $faqs = [];
        foreach (self::listOf($content['faqs'] ?? null) as $row) {
            $faqs[] = ['q' => self::word($row, 'q', $locale), 'a' => self::word($row, 'a', $locale)];
        }
        $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
        $seo = is_array($content['seo'] ?? null) ? $content['seo'] : [];
        $whatsapp = (string) ($content['whatsapp'] ?? '');

        return [
            'hero' => ['title' => self::word($hero, 'title', $locale), 'lead' => self::word($hero, 'lead', $locale)],
            'features' => $features,
            'testimonials' => $testimonials,
            'faqs' => $faqs,
            'sections' => self::sections($content['sections'] ?? []),
            'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
            'seo' => ['title' => self::word($seo, 'title', $locale), 'description' => self::word($seo, 'description', $locale)],
        ];
    }

    /** @param  array<string, mixed>  $content */
    public static function shows(array $content, string $section): bool
    {
        return in_array($section, self::sections($content['sections'] ?? []), true);
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
                $out["{$key}_{$locale}"] = (string) __("site::site.defaults.{$prefix}.{$key}", [], $locale);
            }
        }

        return $out;
    }

    /**
     * A row's word in one language, the other's where it is empty.
     *
     * @param  array<string, mixed>  $row
     */
    private static function word(array $row, string $key, string $locale): string
    {
        $mine = (string) ($row["{$key}_{$locale}"] ?? '');

        return $mine !== '' ? $mine : (string) ($row["{$key}_".($locale === 'ar' ? 'en' : 'ar')] ?? '');
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private static function pairs(mixed $row, array $keys): array
    {
        $row = is_array($row) ? $row : [];
        $out = [];
        foreach ($keys as $key) {
            foreach (self::LOCALES as $locale) {
                $out["{$key}_{$locale}"] = self::text($row["{$key}_{$locale}"] ?? '');
            }
        }

        return $out;
    }

    /**
     * Rows with anything in them, each cleaned.
     *
     * @param  list<string>  $keys  texts kept in both languages
     * @param  ?string  $own  one text in no language (a family's name)
     * @return list<array<string, string>>
     */
    private static function rows(mixed $rows, array $keys, ?string $own): array
    {
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $clean = ($own !== null ? [$own => self::text($row[$own] ?? '')] : []) + self::pairs($row, $keys);
            if (implode('', $clean) !== '') {
                $out[] = $clean;
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private static function listOf(mixed $rows): array
    {
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /** @return list<string> the known sections asked for, in the page's order */
    private static function sections(mixed $asked): array
    {
        $asked = is_array($asked) ? $asked : [];
        $out = [];
        foreach (self::SECTIONS as $section) {
            if (in_array($section, $asked, true)) {
                $out[] = $section;
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

    /** "+20 100 123-4567" → "+201001234567"; "00…" → "+…". */
    private static function phone(mixed $value): string
    {
        $phone = (string) preg_replace('/[\s\-().]/', '', self::text($value));

        return str_starts_with($phone, '00') ? '+'.substr($phone, 2) : $phone;
    }

    /** @return array<string, string> */
    private static function attributes(): array
    {
        $out = [];
        foreach (['hero.title' => 'title', 'hero.lead' => 'lead', 'seo.title' => 'seo_title', 'seo.description' => 'seo_description'] as $path => $field) {
            foreach (self::LOCALES as $locale) {
                $out["{$path}_{$locale}"] = (string) __("site::site.fields.{$field}_{$locale}");
            }
        }
        $out['whatsapp'] = (string) __('site::site.fields.whatsapp');

        return $out;
    }
}
