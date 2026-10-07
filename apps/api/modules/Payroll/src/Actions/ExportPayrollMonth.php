<?php

namespace Modules\Payroll\Actions;

use Carbon\CarbonImmutable;
use Modules\Payroll\Queries\PayrollMonth;
use Modules\Payroll\Support\PayrollMonths;
use Modules\SharedKernel\Money\Money;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The month's payroll as a spreadsheet for whoever pays the teachers
 * (docs/architecture/01, section 13: "تصدير شهري"): one sheet with each
 * teacher's totals, one with every lesson, one with the bonuses and
 * deductions. Amounts are numbers in the currency's units, times are on the
 * academy's clock, and the sheets read right to left in Arabic. A closed
 * month exports the same file every time.
 */
final class ExportPayrollMonth
{
    public function __construct(
        private readonly PayrollMonth $payroll,
        private readonly PayrollMonths $months,
    ) {}

    /** @return string the path of a temporary .xlsx file */
    public function handle(CarbonImmutable $month): string
    {
        $path = tempnam(sys_get_temp_dir(), 'payroll-').'.xlsx';
        $tz = $this->months->timezone();
        $locale = app()->getLocale();
        $rtl = str_starts_with($locale, 'ar');
        $bold = (new Style)->setFontBold();
        $head = (new Style)->setFontBold()->setBackgroundColor('EEF2F7');
        $percent = (new Style)->setFormat('0%');
        $l = fn (string $key, array $r = []) => __('payroll::payroll.export.'.$key, $r);

        $writer = new Writer;
        $writer->openToFile($path);

        $teachers = $this->payroll->teachers($month);
        $state = $this->payroll->state($month);

        // 1. Each teacher's totals.
        $sheet = $writer->getCurrentSheet();
        $sheet->setName($l('summary'));
        $sheet->setSheetView((new SheetView)->setRightToLeft($rtl));
        $sheet->setColumnWidth(28, 1);
        $sheet->setColumnWidthForRange(14, 2, 8);
        $writer->addRow(Row::fromValues([$l('title', ['month' => PayrollMonths::label($month)])], $bold));
        $writer->addRow(Row::fromValues([$state['closed']
            ? $l('closed', ['date' => $state['closed_at']->setTimezone($tz)->format('Y-m-d H:i'), 'by' => $state['closed_by']])
            : $l('open')]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(array_map($l, ['teacher', 'currency', 'lessons', 'hours', 'lesson_pay', 'bonuses', 'deductions', 'total']), $head));
        foreach ($teachers as $t) {
            $writer->addRow(Row::fromValues([
                $t['name'], $t['currency'], $t['lessons'], round($t['minutes'] / 60, 2),
                $this->number($t['lesson_pay']), $this->number($t['bonuses']), $this->number($t['deductions']), $this->number($t['total']),
            ]));
        }
        foreach ($this->payroll->totals($month) as $currency => $total) {
            $rows = array_filter($teachers, fn (array $t) => $t['currency'] === $currency);
            $writer->addRow(Row::fromValues([
                $l('all'), $currency, array_sum(array_column($rows, 'lessons')), round(array_sum(array_column($rows, 'minutes')) / 60, 2),
                $this->number($this->sum($rows, 'lesson_pay', $currency)), $this->number($this->sum($rows, 'bonuses', $currency)),
                $this->number($this->sum($rows, 'deductions', $currency)), $this->number($total),
            ], $bold));
        }

        // 2. Every lesson, and 3. every bonus and deduction, teacher by teacher.
        $lessons = [];
        $adjustments = [];
        foreach ($teachers as $t) {
            if (isset($lessons[$t['teacher_id']])) {
                continue;   // a teacher paid in two currencies: one statement
            }
            $statement = $this->payroll->statement($month, $t['teacher_id']);
            $lessons[$t['teacher_id']] = array_map(fn (array $row) => [$t['name'], $row], $statement['lessons']);
            $adjustments[$t['teacher_id']] = array_map(fn (array $row) => [$t['name'], $row], $statement['adjustments']);
        }

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName($l('lessons_sheet'));
        $sheet->setSheetView((new SheetView)->setRightToLeft($rtl));
        $sheet->setColumnWidth(24, 1, 4);
        $sheet->setColumnWidthForRange(13, 2, 14);
        $sheet->setColumnWidth(30, 15);
        $writer->addRow(Row::fromValues(array_map($l, ['teacher', 'date', 'time', 'student', 'subject', 'type', 'outcome', 'minutes', 'share', 'rate', 'unit', 'amount', 'currency', 'rule', 'remark']), $head));
        foreach (array_merge(...array_values($lessons) ?: [[]]) as [$name, $row]) {
            $line = $row['line'];
            $writer->addRow(Row::fromValuesWithStyles([
                $name,
                $row['lesson_on']->format('Y-m-d'),
                $row['starts_at']?->setTimezone($tz)->format('H:i') ?? '',
                (string) $row['student'],
                $locale === 'ar' ? (string) $row['subject_ar'] : (string) $row['subject_en'],
                $line ? __('payroll::payroll.types.'.$line['type']) : '',
                $line ? __('payroll::payroll.outcomes.'.$line['outcome']) : __('payroll::payroll.statement.corrected'),
                $line['booked_min'] ?? '',
                $line ? $line['pay_pct'] / 100 : '',
                $line && $line['rate'] ? $this->number($line['rate']) : '',
                $line ? __('payroll::payroll.export.units.'.$line['unit']) : '',
                $this->number($row['money']),
                $row['money']->currency,
                $line['rule'] ?? '',
                $this->remark($row),
            ], null, [8 => $percent]));
        }

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName($l('adjustments_sheet'));
        $sheet->setSheetView((new SheetView)->setRightToLeft($rtl));
        $sheet->setColumnWidth(24, 1);
        $sheet->setColumnWidth(16, 2);
        $sheet->setColumnWidth(48, 3);
        $sheet->setColumnWidthForRange(14, 4, 6);
        $writer->addRow(Row::fromValues(array_map($l, ['teacher', 'kind', 'reason', 'amount', 'currency', 'date']), $head));
        foreach (array_merge(...array_values($adjustments) ?: [[]]) as [$name, $row]) {
            $writer->addRow(Row::fromValues([
                $name,
                $row['kind'] === 'reversal' ? __('payroll::payroll.statement.reversal_of', ['kind' => __('payroll::payroll.kinds.'.$row['of'])]) : __('payroll::payroll.kinds.'.$row['kind']),
                (string) $row['note'].($row['reversed'] ? ' ('.__('payroll::payroll.statement.reversed_tag').')' : ''),
                $this->number($row['money']),
                $row['money']->currency,
                $row['at']->setTimezone($tz)->format('Y-m-d'),
            ]));
        }

        $writer->close();

        return $path;
    }

    public function filename(CarbonImmutable $month): string
    {
        return 'payroll-'.$month->format('Y-m').'.xlsx';
    }

    private function number(Money $money): float|int
    {
        $value = $money->decimal();

        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function sum(array $rows, string $key, string $currency): Money
    {
        return array_reduce($rows, fn (Money $carry, array $r) => $carry->plus($r[$key]), Money::of(0, $currency));
    }

    /** @param  array<string, mixed>  $row */
    private function remark(array $row): string
    {
        $parts = [];
        if ($row['earlier']) {
            $parts[] = __('payroll::payroll.statement.earlier', ['month' => PayrollMonths::label($row['lesson_on'])]);
        }
        if ($row['corrected'] && $row['was'] !== null) {
            $parts[] = __('payroll::payroll.statement.was', ['outcome' => __('payroll::payroll.outcomes.'.$row['was']['outcome']), 'pct' => $row['was']['pay_pct'].'%']);
        }

        return implode(' · ', $parts);
    }
}
