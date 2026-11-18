<?php

namespace Modules\Site\Domain\Page;

use Modules\Site\Domain\SiteContent;

/**
 * Pages to start from (site-builder.md, 4.4): online courses, training and
 * professional development, a school for children and their families, a
 * language school, and a blank page. Each is a list of blocks with their
 * layouts and tones, worded in Modern Standard Arabic and English
 * (site::templates) without any academy's name (the page adds it), and the
 * portal's own words left where they fit. No files: a layout that needs a
 * picture shows without one until the academy adds it, and the blocks that
 * cannot show without files (logos, a gallery) start hidden. Each passes
 * PublishRules with no blockers. Nothing here is about one subject.
 */
final class Templates
{
    public const KEYS = ['online_courses', 'training', 'kids_families', 'language_school', 'blank'];

    /** The template a new page starts from, by the audience it speaks to. */
    public static function forAudience(string $audience): string
    {
        return $audience === 'adults' ? 'online_courses' : 'kids_families';
    }

    /**
     * @param  string  $audience  the page's audience now (the blank page keeps it)
     * @return array{v: int, settings: array<string, mixed>, blocks: list<array<string, mixed>>}
     */
    public static function make(string $key, string $audience = 'families'): array
    {
        return match ($key) {
            'online_courses' => self::onlineCourses(),
            'training' => self::training(),
            'kids_families' => self::kidsFamilies(),
            'language_school' => self::languageSchool(),
            default => self::blank($audience),
        };
    }

    private static function onlineCourses(): array
    {
        $k = 'online_courses';

        return PageDoc::normalize([
            'settings' => ['audience' => 'adults', 'mood' => 'light', 'motion' => 'subtle', 'header' => ['style' => 'solid'], 'corners' => 'soft'],
            'blocks' => [
                self::block('hero', 'split', ['space' => 'roomy', 'width' => 'wide'], [
                    'kicker' => self::t("{$k}.hero.kicker"),
                    'title' => self::t("{$k}.hero.title"),
                    'lead' => self::t("{$k}.hero.lead"),
                    'trust' => self::lines("{$k}.hero.trust", 3),
                    'media_shape' => 'arch',
                ]),
                self::block('stats', 'band', ['space' => 'compact'], ['items' => self::items("{$k}.stats", 3, ['value', 'label'])]),
                self::block('subjects', 'cards', [], ['title' => self::t("{$k}.subjects.title"), 'lead' => self::t("{$k}.subjects.lead")], self::t("{$k}.subjects.nav")),
                self::block('cards', 'list', ['tone' => 'white'], [
                    'title' => self::t("{$k}.cards.title"), 'lead' => self::t("{$k}.cards.lead"),
                    'items' => self::cards("{$k}.cards", 3, null), 'columns' => 3,
                ]),
                self::block('features', 'icons', ['tone' => 'tint'], [
                    'title' => self::t("{$k}.features.title"), 'lead' => self::t("{$k}.features.lead"),
                    'items' => self::icons("{$k}.features", ['laptop', 'users', 'clock', 'report', 'award', 'chat']),
                ], self::t("{$k}.features.nav")),
                self::block('teachers', 'portraits', ['tone' => 'paper'], ['title' => self::t("{$k}.teachers.title"), 'show_ages' => false], self::t("{$k}.teachers.nav")),
                self::block('pricing', 'cards', [], [], self::t("{$k}.pricing.nav")),
                self::block('testimonials', 'grid', ['tone' => 'paper'], ['items' => []]),
                self::block('faq', 'two_columns', [], ['items' => self::items("{$k}.faq", 5, ['q', 'a'])], self::t("{$k}.faq.nav")),
                self::block('cta', 'banner', [], [
                    'title' => self::t("{$k}.cta.title"), 'lead' => self::t("{$k}.cta.lead"),
                    'primary' => ['label' => self::t("{$k}.cta.button"), 'action' => ['kind' => 'trial']],
                ]),
                self::block('form', 'centered', ['tone' => 'paper'], []),
                self::block('contact', 'band', ['tone' => 'white', 'space' => 'compact'], []),
            ],
        ]);
    }

    private static function training(): array
    {
        $k = 'training';

        return PageDoc::normalize([
            'settings' => ['audience' => 'adults', 'mood' => 'light', 'motion' => 'subtle', 'corners' => 'sharp',
                'header' => ['style' => 'overlay', 'cta' => ['label' => self::t("{$k}.cta.button"), 'action' => ['kind' => 'trial']]]],
            'blocks' => [
                self::block('hero', 'video', ['space' => 'roomy'], [
                    'title' => self::t("{$k}.hero.title"), 'lead' => self::t("{$k}.hero.lead"),
                    'kicker' => self::t("{$k}.hero.kicker"),
                    // The page asks for a first meeting, not a trial lesson: every button says so.
                    'primary' => ['label' => self::t("{$k}.cta.button"), 'action' => ['kind' => 'trial']],
                    'secondary' => null, 'trust' => [], 'scrim' => 'strong',
                ]),
                self::block('logos', 'row', ['space' => 'compact'], ['eyebrow' => self::t("{$k}.logos.title"), 'items' => []], null, hidden: true),
                self::block('cards', 'grid', ['tone' => 'paper'], [
                    'title' => self::t("{$k}.cards.title"), 'lead' => self::t("{$k}.cards.lead"),
                    'items' => self::cards("{$k}.cards", 3, self::t("{$k}.cards.button")), 'columns' => 3,
                ], self::t("{$k}.cards.nav")),
                self::block('features', 'list', [], [
                    'title' => self::t("{$k}.features.title"), 'lead' => self::t("{$k}.features.lead"),
                    'items' => self::icons("{$k}.features", ['check', 'check', 'check', 'check']), 'columns' => 2,
                ], self::t("{$k}.features.nav")),
                self::block('image_text', 'side', ['tone' => 'paper'], [
                    'title' => self::t("{$k}.image_text.title"),
                    'body' => ['ar' => [['t' => 'p', 'c' => [['text' => self::t("{$k}.image_text.body")['ar']]]]], 'en' => [['t' => 'p', 'c' => [['text' => self::t("{$k}.image_text.body")['en']]]]]],
                    'bullets' => self::lines("{$k}.image_text.bullets", 3),
                    'media_side' => 'end',
                ]),
                self::block('steps', 'line', [], ['title' => self::t("{$k}.steps.title"), 'items' => self::items("{$k}.steps", 4, ['title', 'text'])], self::t("{$k}.steps.nav")),
                self::block('stats', 'cards', ['tone' => 'tint'], ['items' => self::items("{$k}.stats", 3, ['value', 'label'])]),
                self::block('testimonials', 'single', [], ['items' => []]),
                self::block('cta', 'split', [], [
                    'title' => self::t("{$k}.cta.title"), 'lead' => self::t("{$k}.cta.lead"),
                    'primary' => ['label' => self::t("{$k}.cta.button"), 'action' => ['kind' => 'trial']],
                ]),
                self::block('form', 'split', [], ['title' => self::t("{$k}.form.title")]),
            ],
        ]);
    }

    /** Today's page for children and their families, as it was before the builder. */
    private static function kidsFamilies(): array
    {
        $content = SiteContent::defaults();
        $content['audience'] = 'families';
        $content['sections'] = SiteContent::SECTIONS;

        return LegacyImport::fromContent($content);
    }

    private static function languageSchool(): array
    {
        $k = 'language_school';

        return PageDoc::normalize([
            'settings' => ['audience' => 'adults', 'mood' => 'warm', 'motion' => 'subtle', 'header' => ['style' => 'overlay'], 'corners' => 'round'],
            'blocks' => [
                self::block('hero', 'centered', ['align' => 'center', 'space' => 'roomy'], [
                    'kicker' => self::t("{$k}.hero.kicker"), 'title' => self::t("{$k}.hero.title"), 'lead' => self::t("{$k}.hero.lead"),
                ]),
                self::block('subjects', 'chips', ['align' => 'center', 'space' => 'compact'], ['title' => self::t("{$k}.subjects.title"), 'show_programs' => false], self::t("{$k}.subjects.nav")),
                self::block('features', 'cards', ['tone' => 'paper'], [
                    'title' => self::t("{$k}.features.title"), 'lead' => self::t("{$k}.features.lead"),
                    'items' => self::icons("{$k}.features", ['chat', 'globe', 'book', 'clock', 'users', 'award']),
                ], self::t("{$k}.features.nav")),
                self::block('teachers', 'cards', [], ['title' => self::t("{$k}.teachers.title"), 'show_ages' => false], self::t("{$k}.teachers.nav")),
                self::block('gallery', 'masonry', ['tone' => 'paper'], ['title' => self::t("{$k}.gallery.title"), 'items' => []], null, hidden: true),
                self::block('pricing', 'table', [], [], self::t("{$k}.pricing.nav")),
                self::block('faq', 'accordion', ['tone' => 'paper'], ['items' => self::items("{$k}.faq", 4, ['q', 'a'])]),
                self::block('form', 'split', [], []),
            ],
        ]);
    }

    private static function blank(string $audience): array
    {
        return PageDoc::normalize([
            'settings' => ['audience' => $audience, 'mood' => 'light', 'motion' => 'subtle', 'header' => ['style' => 'solid']],
            'blocks' => [
                self::block('hero', 'minimal', [], ['title' => self::t('blank.hero.title'), 'secondary' => null]),
                self::block('form', 'centered', ['tone' => 'paper'], []),
            ],
        ]);
    }

    /** @return array<string, mixed> a block as the template writes it (PageDoc makes its id) */
    private static function block(string $type, string $variant, array $style, array $data, ?array $nav = null, bool $hidden = false): array
    {
        return ['type' => $type, 'variant' => $variant, 'hidden' => $hidden, 'nav' => $nav ?? ['ar' => '', 'en' => ''], 'style' => $style, 'data' => $data];
    }

    /** @return array{ar: string, en: string} */
    private static function t(string $key): array
    {
        return ['ar' => (string) __("site::templates.{$key}", [], 'ar'), 'en' => (string) __("site::templates.{$key}", [], 'en')];
    }

    /** @return list<array{ar: string, en: string}> */
    private static function lines(string $key, int $count): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = self::t("{$key}.{$i}");
        }

        return $out;
    }

    /**
     * @param  list<string>  $keys
     * @return list<array<string, array{ar: string, en: string}>>
     */
    private static function items(string $key, int $count, array $keys): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $item = [];
            foreach ($keys as $field) {
                $item[$field] = self::t("{$key}.items.{$i}.{$field}");
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * Cards of the academy's own courses or programmes, with no pictures (the academy adds its own).
     *
     * @param  array{ar: string, en: string}|null  $button  each card's button to the form, or none
     * @return list<array<string, mixed>>
     */
    private static function cards(string $key, int $count, ?array $button): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = [
                'media' => null,
                'label' => self::t("{$key}.items.{$i}.label"),
                'title' => self::t("{$key}.items.{$i}.title"),
                'text' => self::t("{$key}.items.{$i}.text"),
                'link' => $button === null ? null : ['label' => $button, 'action' => ['kind' => 'trial']],
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $icons
     * @return list<array<string, mixed>>
     */
    private static function icons(string $key, array $icons): array
    {
        $out = [];
        foreach ($icons as $i => $icon) {
            $out[] = ['icon' => $icon, 'title' => self::t("{$key}.items.{$i}.title"), 'text' => self::t("{$key}.items.{$i}.text")];
        }

        return $out;
    }
}
