<?php

namespace Modules\Scheduling\Filament;

use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\SharedKernel\Time\Clock;

/**
 * How a group reads on the back office's screens (Groups, a group's page,
 * Find a teacher's group mode): its weekly times in the week's order with
 * days that share a time together ("السبت والثلاثاء 6:00 م"), its status as
 * a pill, and its shape. One visual language: group is the "info" tone with
 * a people icon and seat dots, private a plain badge.
 */
final class GroupView
{
    /** Saturday first in Arabic, Monday first otherwise. */
    public static function weekOrder(): array
    {
        return app()->getLocale() === 'ar' ? [6, 7, 1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7];
    }

    /** @param  list<array{weekday: int, start: string}>  $times */
    public static function timesLine(array $times): string
    {
        if ($times === []) {
            return '—';
        }
        $order = array_flip(self::weekOrder());
        usort($times, fn (array $a, array $b) => [$order[$a['weekday']], $a['start']] <=> [$order[$b['weekday']], $b['start']]);

        return collect($times)->groupBy('start', preserveKeys: false)
            ->map(fn ($same, $start) => $same->map(fn ($t) => __('scheduling::shifts.days.'.$t['weekday']))->unique()->implode(__('scheduling::find.free.and')).' '.Clock::wall((string) $start))
            ->implode(' · ');
    }

    /** "30 دقيقة · حصتان في الأسبوع" */
    public static function shape(int $minutes, int $perWeek): string
    {
        return __('scheduling::group_screens.card.shape', ['minutes' => $minutes, 'per_week' => trans_choice('scheduling::find.per_week', $perWeek, ['n' => $perWeek])]);
    }

    /** The pill's colour: running is success, forming waits (warning), ended is gray. */
    public static function tone(string $status): string
    {
        return match ($status) {
            LessonGroup::RUNNING => 'success',
            LessonGroup::FORMING => 'warning',
            default => 'gray',
        };
    }

    public static function status(string $status): string
    {
        return __('scheduling::group_screens.status.'.$status);
    }

    /** "3 من 5 مقاعد" for screen readers and the line beside the dots. */
    public static function seatsLabel(int $taken, int $capacity): string
    {
        return __('scheduling::group_screens.card.seats', ['taken' => $taken, 'capacity' => $capacity]);
    }
}
