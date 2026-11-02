<?php

namespace Modules\Communication\Templates;

use Carbon\CarbonImmutable;
use Modules\Communication\Contracts\DTOs\Day;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\DTOs\WeekdayTime;
use Modules\Communication\Contracts\DTOs\WeeklyTimes;
use Modules\Communication\Models\TemplateWording;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\TimeFormat;
use Modules\SharedKernel\Time\ViewerClock;

/**
 * Writes a template for one person: the academy's wording if it has one in
 * their language, else the module's; :placeholders filled with times on
 * their clock and in their way of reading one (12 or 24 hours; the academy's
 * when not given), and days, amounts and words in their language.
 */
final class Renderer
{
    /** @var array<string, array{title: string, body: string}|null> */
    private array $wordings = [];

    /** @param  array<string, mixed>  $data */
    public function render(TemplateDefinition $template, string $locale, array $data, string $timezone, ?TimeFormat $clock = null): Rendered
    {
        [$title, $body] = $this->wording($template, $locale);
        $clock ??= app(ViewerClock::class)->academy();
        $replace = [];
        foreach ($data as $name => $value) {
            $replace[':'.$name] = $this->format($value, $locale, $timezone, $clock);
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

    private function format(mixed $value, string $locale, string $timezone, TimeFormat $clock): string
    {
        return match (true) {
            $value instanceof CarbonImmutable => $value->setTimezone($timezone)->locale($locale)->translatedFormat(Clock::pattern((string) trans()->get('shared::time.label', [], $locale), $clock)),
            $value instanceof WeekdayTime => $value->local->locale($locale)->translatedFormat(Clock::pattern('l H:i', $clock)),
            $value instanceof WeeklyTimes => $this->weekly($value, $locale, $timezone, $clock),
            $value instanceof Day => CarbonImmutable::parse($value->date, $timezone)->locale($locale)->translatedFormat('l j F'),
            $value instanceof Money => $value->format($locale),
            $value instanceof Localized => $value->in($locale),
            $value instanceof Translatable => $value->count === null
                ? (string) trans()->get($value->key, $value->replace, $locale)
                : trans()->choice($value->key, $value->count, $value->replace + ['count' => $value->count], $locale),
            $value === null => '',
            default => (string) $value,
        };
    }

    /** Each weekly time on the recipient's clock, from Saturday in Arabic (Monday otherwise), joined as a list. */
    private function weekly(WeeklyTimes $times, string $locale, string $timezone, TimeFormat $clock): string
    {
        $local = array_map(fn (CarbonImmutable $at) => $at->setTimezone($timezone), $times->starts);
        $first = $locale === 'ar' ? 6 : 1;
        usort($local, fn (CarbonImmutable $a, CarbonImmutable $b) => [($a->dayOfWeekIso - $first + 7) % 7, $a->format('H:i')] <=> [($b->dayOfWeekIso - $first + 7) % 7, $b->format('H:i')]);
        $words = array_map(fn (CarbonImmutable $at) => $at->locale($locale)->translatedFormat(Clock::pattern('l H:i', $clock)), $local);
        if (count($words) < 2) {
            return (string) ($words[0] ?? '');
        }
        $last = array_pop($words);

        return implode($locale === 'ar' ? '، ' : ', ', $words).($locale === 'ar' ? ' و' : ' and ').$last;
    }
}
