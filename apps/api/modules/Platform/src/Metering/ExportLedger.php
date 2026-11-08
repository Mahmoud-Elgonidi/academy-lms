<?php

namespace Modules\Platform\Metering;

use Carbon\CarbonImmutable;
use Modules\SharedKernel\Plans\Meter;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The academy's usage ledger as a spreadsheet: a sheet for each meter, every
 * line of it oldest first (holds left out), with what it was, the amount,
 * the balance after it, and a lot's end. Days and times on the academy's
 * clock (24 hours, as data keeps them); the sheets read right to left in
 * Arabic.
 */
final class ExportLedger
{
    public const MAX_LINES = 20000;

    public function __construct(private readonly UsageReport $report, private readonly Meters $meters) {}

    /**
     * @param  list<Meter>  $meters
     * @return string the path of a temporary .xlsx file
     */
    public function handle(string $academyId, array $meters): string
    {
        $path = tempnam(sys_get_temp_dir(), 'usage-').'.xlsx';
        $tz = $this->meters->zone($academyId);
        $rtl = str_starts_with(app()->getLocale(), 'ar');
        $head = (new Style)->setFontBold()->setBackgroundColor('EEF2F7');
        $l = fn (string $key, array $r = []) => __('platform::metering.export.'.$key, $r);

        $writer = new Writer;
        $writer->openToFile($path);
        foreach (array_values($meters) as $i => $meter) {
            $sheet = $i === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName(mb_substr($meter->label(), 0, 31));
            $sheet->setSheetView((new SheetView)->setRightToLeft($rtl));
            $sheet->setColumnWidthForRange(13, 1, 2);
            $sheet->setColumnWidth(40, 3);
            $sheet->setColumnWidthForRange(14, 4, 6);
            $writer->addRow(Row::fromValues([$l('title', ['meter' => $meter->label()])], (new Style)->setFontBold()));
            $writer->addRow(Row::fromValues([$l('made', ['date' => CarbonImmutable::now()->setTimezone($tz)->format('Y-m-d H:i'), 'timezone' => $tz])]));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(array_map($l, ['date', 'time', 'what', 'amount', 'after', 'expires']), $head));
            foreach (array_reverse($this->report->statement($academyId, $meter, self::MAX_LINES)) as $line) {
                $at = $line['at']->setTimezone($tz);
                $writer->addRow(Row::fromValues([
                    $at->format('Y-m-d'), $at->format('H:i'), MeterFormat::line($line), $line['amount'], $line['after'],
                    $line['expires_at']?->subSecond()->setTimezone($tz)->format('Y-m-d') ?? '',
                ]));
            }
        }
        $writer->close();

        return $path;
    }

    public function filename(): string
    {
        return 'usage-'.CarbonImmutable::now()->toDateString().'.xlsx';
    }
}
