<?php

namespace Modules\Curriculum\Outline;

use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

/**
 * An outline as a spreadsheet, with the library the platform's imports read
 * with (OpenSpout): the first sheet of an .xlsx, or a UTF-8 .csv, as rows of
 * text (TextOutline::fromSheet reads them), and the template to fill: the
 * columns level, unit, lesson and objective, titled in the reader's language,
 * with a few example rows.
 */
final class OutlineSheet
{
    /** Rows read at most: more than an outline can hold. */
    public const ROWS_MAX = 600;

    public const TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
    ];

    /** @return ?list<list<string>> null when the file cannot be read as a sheet */
    public static function read(string $path, string $fileName): ?array
    {
        $csv = (bool) preg_match('/\.(csv|txt)$/i', $fileName);
        try {
            $reader = $csv ? new CsvReader(self::csvOptions($path)) : new XlsxReader;
            $reader->open($path);
            $rows = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn ($v) => self::text($v), $row->toArray());
                    if (implode('', $cells) !== '') {
                        $rows[] = $cells;
                    }
                    if (count($rows) >= self::ROWS_MAX) {
                        break 2;
                    }
                }
                break;
            }
            $reader->close();
        } catch (Throwable) {
            return null;
        }

        return $rows;
    }

    /** @return list<string> the template's titles in both languages, so either is skipped when read */
    public static function headers(): array
    {
        return array_values(array_unique([...self::titles('ar'), ...self::titles('en')]));
    }

    /** @return string a temporary .xlsx */
    public static function template(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'outline-').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName(mb_substr((string) __('curriculum::outline.sheet.name'), 0, 31));
        $sheet->setSheetView((new SheetView)->setRightToLeft(str_starts_with(app()->getLocale(), 'ar')));
        $sheet->setColumnWidthForRange(28, 1, 4);
        $writer->addRow(Row::fromValues(self::titles(app()->getLocale()), (new Style)->setFontBold()->setBackgroundColor('EEF2F7')));
        foreach ((array) __('curriculum::outline.sheet.examples') as $example) {
            $writer->addRow(Row::fromValues(array_map('strval', (array) $example)));
        }
        $writer->close();

        return $path;
    }

    /** @return list<string> */
    private static function titles(string $locale): array
    {
        return array_map(fn ($k) => (string) __('curriculum::outline.sheet.columns.'.$k, [], $locale), ['level', 'unit', 'lesson', 'objective']);
    }

    private static function text(mixed $v): string
    {
        return match (true) {
            $v instanceof DateTimeInterface => $v->format('Y-m-d'),
            is_float($v) && floor($v) === $v && abs($v) < 1e15 => number_format($v, 0, '.', ''),
            is_bool($v) => $v ? '1' : '0',
            default => trim(str_replace("\u{FEFF}", '', (string) $v)),
        };
    }

    private static function csvOptions(string $path): CsvOptions
    {
        $first = (string) fgets(fopen($path, 'r') ?: throw new \RuntimeException('Unreadable file.'));
        $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
        arsort($counts);
        $options = new CsvOptions;
        $options->FIELD_DELIMITER = (string) array_key_first($counts);

        return $options;
    }
}
