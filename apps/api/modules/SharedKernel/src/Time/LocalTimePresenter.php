<?php

namespace Modules\SharedKernel\Time;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * How every instant leaves the API:
 *
 *     {"utc": "2026-10-05T19:00:00Z", "local": "2026-10-05T22:00:00+03:00",
 *      "timezone": "Asia/Riyadh", "label": "الأحد 5 أكتوبر · 22:00"}
 *
 * `utc` is the truth; `local` and `label` are in the viewer's timezone unless
 * another one is passed (to show "the student's time" next to yours), and
 * `label`'s clock is the viewer's way (Clock: 12 or 24 hours).
 */
final class LocalTimePresenter
{
    public function __construct(private readonly ViewerTimezone $viewer) {}

    /** @return array{utc: string, local: string, timezone: string, label: string}|null */
    public function present(?CarbonInterface $instant, ?string $timezone = null): ?array
    {
        if ($instant === null) {
            return null;
        }

        $timezone ??= $this->viewer->effective();
        $local = CarbonImmutable::instance($instant)->setTimezone($timezone)->locale(app()->getLocale());

        return [
            'utc' => CarbonImmutable::instance($instant)->utc()->format('Y-m-d\TH:i:s\Z'),
            'local' => $local->format('Y-m-d\TH:i:sP'),
            'timezone' => $timezone,
            'label' => $local->translatedFormat(Clock::pattern((string) __('shared::time.label'))),
        ];
    }

    /**
     * A day on the academy's calendar (a course's last paused day, its end):
     * the moment it begins there, on the academy's clock, so `local` holds
     * that very date wherever the viewer is, and `label` names the day alone.
     *
     * @return array{utc: string, local: string, timezone: string, label: string}|null
     */
    public function academyDay(CarbonInterface|string|null $day): ?array
    {
        if ($day === null) {
            return null;
        }
        $timezone = $this->viewer->academy() ?? 'UTC';
        $date = $day instanceof CarbonInterface ? $day->toDateString() : substr($day, 0, 10);
        $start = (new LocalTimeResolver)->resolve($date, '00:00', $timezone)->utc;

        $at = (array) $this->present($start, $timezone);
        $at['label'] = CarbonImmutable::parse($date)->locale(app()->getLocale())->translatedFormat((string) __('shared::time.day'));

        return $at;
    }
}
