<?php

namespace Modules\Site\Domain\Page;

use Modules\Site\Domain\SiteContent;

/**
 * `GET v1/site`'s old `content`, made from the published page for one more
 * release (site-builder.md, 3.4): the same shape SiteContent::localized()
 * gave, so a portal from before the builder still renders. The opening's
 * words from the first hero; the reasons and their heading from the first
 * features block; what people said and the questions from the first blocks
 * of those types; the old section names whose blocks show ("teach": the
 * form's teach tab); WhatsApp, search words and the audience from the
 * settings. LegacyContent::from(LegacyImport::fromContent(x)) is
 * SiteContent::localized(x). Plain loops (Scramble). Pure.
 */
final class LegacyContent
{
    /** The old section names and the block types they were. */
    private const SECTION_TYPES = ['subjects' => 'subjects', 'teachers' => 'teachers', 'pricing' => 'pricing', 'app' => 'app', 'steps' => 'steps', 'testimonials' => 'testimonials', 'faq' => 'faq'];

    /**
     * @param  array<string, mixed>  $doc
     * @return array{hero: array{title: string, lead: string}, audience: string, why: array{title: string, lead: string}, features: list<array{title: string, text: string}>, testimonials: list<array{name: string, role: string, quote: string}>, faqs: list<array{q: string, a: string}>, sections: list<string>, whatsapp: ?string, seo: array{title: string, description: string}}
     */
    public static function from(array $doc, string $locale): array
    {
        $locale = in_array($locale, Clean::LOCALES, true) ? $locale : 'ar';
        $first = [];
        $visible = [];
        $teach = false;
        foreach (is_array($doc['blocks'] ?? null) ? $doc['blocks'] : [] as $block) {
            $type = (string) ($block['type'] ?? '');
            $first[$type] ??= $block;
            if (! ($block['hidden'] ?? false)) {
                $visible[$type] = true;
                if ($type === 'form' && ($block['data']['teach'] ?? false)) {
                    $teach = true;
                }
            }
        }
        $w = fn (mixed $pair): string => Localize::word($pair, $locale) ?? '';

        $features = [];
        foreach ($first['features']['data']['items'] ?? [] as $item) {
            $features[] = ['title' => $w($item['title'] ?? null), 'text' => $w($item['text'] ?? null)];
        }
        $testimonials = [];
        foreach ($first['testimonials']['data']['items'] ?? [] as $item) {
            $testimonials[] = ['name' => (string) ($item['name'] ?? ''), 'role' => $w($item['role'] ?? null), 'quote' => $w($item['quote'] ?? null)];
        }
        $faqs = [];
        foreach ($first['faq']['data']['items'] ?? [] as $item) {
            $faqs[] = ['q' => $w($item['q'] ?? null), 'a' => $w($item['a'] ?? null)];
        }
        $sections = [];
        foreach (SiteContent::SECTIONS as $section) {
            if ($section === 'teach' ? $teach : isset($visible[self::SECTION_TYPES[$section]])) {
                $sections[] = $section;
            }
        }
        $settings = is_array($doc['settings'] ?? null) ? $doc['settings'] : [];
        $whatsapp = (string) ($settings['whatsapp'] ?? '');

        return [
            'hero' => ['title' => $w($first['hero']['data']['title'] ?? null), 'lead' => $w($first['hero']['data']['lead'] ?? null)],
            'audience' => in_array($settings['audience'] ?? null, SiteContent::AUDIENCES, true) ? $settings['audience'] : 'families',
            'why' => ['title' => $w($first['features']['data']['title'] ?? null), 'lead' => $w($first['features']['data']['lead'] ?? null)],
            'features' => $features,
            'testimonials' => $testimonials,
            'faqs' => $faqs,
            'sections' => $sections,
            'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
            'seo' => ['title' => $w($settings['seo']['title'] ?? null), 'description' => $w($settings['seo']['description'] ?? null)],
        ];
    }
}
