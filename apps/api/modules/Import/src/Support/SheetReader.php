<?php

namespace Modules\Import\Support;

use DateTimeInterface;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * The first sheet of an .xlsx, or a .csv (comma, semicolon or tab; UTF-8,
 * or Windows Arabic as older Excel saves it), as rows of text. Dates become
 * "Y-m-d", whole numbers lose ".0", empty rows are dropped.
 */
final class SheetReader
{
    /** @return list<list<string>> */
    public function read(string $path, string $fileName, int $limit): array
    {
        $csv = str_ends_with(mb_strtolower($fileName), '.csv') || str_ends_with(mb_strtolower($fileName), '.txt');
        $reader = $csv ? new CsvReader($this->csvOptions($path)) : new XlsxReader;
        $source = $csv ? $this->utf8($path) : $path;

        $rows = [];
        $reader->open($source);
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(fn ($v) => $this->text($v), $row->toArray());
                if (implode('', $cells) === '') {
                    continue;
                }
                $rows[] = $cells;
                if (count($rows) > $limit) {
                    break 2;
                }
            }
            break;
        }
        $reader->close();
        if ($source !== $path) {
            @unlink($source);
        }

        return $rows;
    }

    private function text(mixed $v): string
    {
        return match (true) {
            $v instanceof DateTimeInterface => $v->format('H:i:s') === '00:00:00' ? $v->format('Y-m-d') : $v->format('Y-m-d H:i'),
            is_float($v) && floor($v) === $v && abs($v) < 1e15 => number_format($v, 0, '.', ''),
            is_bool($v) => $v ? '1' : '0',
            default => trim(str_replace("\u{FEFF}", '', (string) $v)),
        };
    }

    private function csvOptions(string $path): CsvOptions
    {
        $first = (string) fgets(fopen($path, 'r') ?: throw new RuntimeException('Unreadable file.'));
        $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), "\t" => substr_count($first, "\t")];
        arsort($counts);
        $options = new CsvOptions;
        $options->FIELD_DELIMITER = (string) array_key_first($counts);

        return $options;
    }

    /** A copy in UTF-8 when the file is in Windows Arabic. */
    private function utf8(string $path): string
    {
        $content = (string) file_get_contents($path);
        if (mb_check_encoding($content, 'UTF-8')) {
            return $path;
        }
        $copy = tempnam(sys_get_temp_dir(), 'import-').'.csv';
        file_put_contents($copy, (string) iconv('CP1256', 'UTF-8//IGNORE', $content));

        return $copy;
    }
}
