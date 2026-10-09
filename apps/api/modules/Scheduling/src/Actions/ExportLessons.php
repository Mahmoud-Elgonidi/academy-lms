<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Lang;
use Modules\Scheduling\Queries\Calendar\CalendarFilter;
use Modules\Scheduling\Queries\Calendar\CalendarRange;
use Modules\Scheduling\Queries\Calendar\LessonsCalendar;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The calendar's range as a spreadsheet: the lessons counted by status, by
 * teacher and by subject, then every lesson (all of them, where the screen's
 * list stops). Days and times on the calendar's clock, said at the top; the
 * sheets read right to left in Arabic.
 */
final class ExportLessons
{
    public function __construct(private readonly LessonsCalendar $calendar) {}

    /** @return string the path of a temporary .xlsx file */
    public function handle(CalendarRange $range, CalendarFilter $filter): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lessons-').'.xlsx';
        $tz = $range->timezone;
        [$from, $to] = $range->bounds();
        $rtl = str_starts_with(app()->getLocale(), 'ar');
        $bold = (new Style)->setFontBold();
        $head = (new Style)->setFontBold()->setBackgroundColor('EEF2F7');
        $l = fn (string $key, array $r = []) => __('scheduling::calendar.export.'.$key, $r);
        $status = fn (string $s) => __('scheduling::calendar.statuses.'.$s);
        $statuses = LessonsCalendar::statuses();

        $writer = new Writer;
        $writer->openToFile($path);

        // 1. How many lessons in each status: overall, by teacher, by subject.
        $sheet = $writer->getCurrentSheet();
        $sheet->setName($l('summary'));
        $sheet->setSheetView((new SheetView)->setRightToLeft($rtl));
        $sheet->setColumnWidth(30, 1);
        $sheet->setColumnWidthForRange(14, 2, count($statuses) + 3);
        $writer->addRow(Row::fromValues([$l('title', ['from' => $range->first->toDateString(), 'to' => $range->last->toDateString()])], $bold));
        $writer->addRow(Row::fromValues([$l('made', ['date' => CarbonImmutable::now()->setTimezone($tz)->format('Y-m-d H:i'), 'timezone' => $tz])]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([$l('status'), $l('lessons'), $l('hours')], $head));
        foreach ($this->calendar->byStatus($filter, $from, $to) as $s => $c) {
            $writer->addRow(Row::fromValues([$status($s), $c['count'], round($c['minutes'] / 60, 2)]));
        }
        foreach (['teacher' => $this->calendar->byTeacher($filter, $from, $to), 'subject' => $this->calendar->bySubject($filter, $from, $to)] as $by => $rows) {
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([$l($by), ...array_map($status, $statuses), $l('total'), $l('hours')], $head));
            foreach ($rows as $r) {
                $writer->addRow(Row::fromValues([$r['name'], ...array_values($r['statuses']), $r['total'], round($r['minutes'] / 60, 2)]));
            }
        }

        // 2. Every lesson, earliest first.
        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName($l('lessons'));
        $sheet->setSheetView((new SheetView)->setRightToLeft($rtl));
        $sheet->setColumnWidthForRange(12, 1, 4);
        $sheet->setColumnWidthForRange(24, 5, 7);
        $sheet->setColumnWidthForRange(16, 8, 12);
        $writer->addRow(Row::fromValues(array_map($l, ['date', 'start', 'end', 'minutes', 'teacher', 'student', 'subject', 'type', 'status', 'funding', 'reason', 'instead_of']), $head));
        $ar = app()->getLocale() === 'ar';
        foreach ($this->calendar->lessons($filter, $from, $to) as $r) {
            $start = $r->starts_at->setTimezone($tz);
            $writer->addRow(Row::fromValues([
                $start->format('Y-m-d'), $start->format('H:i'), $r->ends_at->setTimezone($tz)->format('H:i'), $r->minutes,
                (string) $r->teacher, (string) ($r->student ?? ''), (string) ($ar ? $r->subject_ar : $r->subject_en),
                __('scheduling::calendar.types.'.$r->type), $status($r->status), __('scheduling::calendar.funding.'.$r->funding_status),
                $r->status === 'cancelled' && $r->reason ? (Lang::has('scheduling::calendar.reasons.'.$r->reason) ? __('scheduling::calendar.reasons.'.$r->reason) : $r->reason) : '',
                (string) ($r->original_teacher ?? ''),
            ]));
        }

        $writer->close();

        return $path;
    }

    public function filename(CalendarRange $range): string
    {
        return 'lessons-'.$range->first->toDateString().'-'.$range->last->toDateString().'.xlsx';
    }
}
