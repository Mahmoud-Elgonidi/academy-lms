<?php

namespace Modules\Communication\Templates;

use Carbon\CarbonImmutable;
use Modules\Communication\Contracts\DTOs\Day;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Models\TemplateWording;
use Modules\SharedKernel\Money\Money;

/**
 * Writes a template for one person: the academy's wording if it has one in
 * their language, else the module's; :placeholders filled with times on
 * their clock, and days, amounts and words in their language.
 */
final class Renderer
{
    /** @var array<string, array{title: string, body: string}|null> */
    private array $wordings = [];

    /** @param  array<string, mixed>  $data */
    public function render(TemplateDefinition $template, string $locale, array $data, string $timezone): Rendered
    {
        [$title, $body] = $this->wording($template, $locale);
        $replace = [];
        foreach ($data as $name => $value) {
            $replace[':'.$name] = $this->format($value, $locale, $timezone);
        }
        // Longest first, so ":teacher_name" is not eaten by ":teacher".
        uksort($replace, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return new Rendered(trim(strtr($title, $replace)), trim(strtr($body, $replace)));
    }

    /** @return array{0: string, 1: string} the academy's wording, else the module default */
    public function wording(TemplateDefinition $template, string $locale): array
    {
        $own = $this->wordings["{$template->key}|{$locale}"] ??= TemplateWording::query()
            ->where('template_key', $template->key)->where('locale', $locale)->first(['title', 'body'])?->only(['title', 'body']);

        return $own !== null
            ? [$own['title'], $own['body']]
            : [(string) trans()->get($template->title, [], $locale), (string) trans()->get($template->body, [], $locale)];
    }

    public function forget(): void
    {
        $this->wordings = [];
    }

    private function format(mixed $value, string $locale, string $timezone): string
    {
        return match (true) {
            $value instanceof CarbonImmutable => $value->setTimezone($timezone)->locale($locale)->translatedFormat((string) trans()->get('shared::time.label', [], $locale)),
            $value instanceof Day => CarbonImmutable::parse($value->date, $timezone)->locale($locale)->translatedFormat('l j F'),
            $value instanceof Money => $value->format($locale),
            $value instanceof Localized => $value->in($locale),
            $value instanceof Translatable => (string) trans()->get($value->key, $value->replace, $locale),
            $value === null => '',
            default => (string) $value,
        };
    }
}
