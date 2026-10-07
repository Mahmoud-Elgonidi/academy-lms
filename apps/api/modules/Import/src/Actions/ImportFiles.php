<?php

namespace Modules\Import\Actions;

use Modules\Import\Importers\Importers;
use Modules\Import\Models\ImportRun;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The spreadsheets around an import: a template for each kind (the columns
 * it reads, titled in the reader's language, with two example rows), and a
 * file of the rows that did not go in, as they were, with the reason, to fix
 * and bring in again.
 */
final class ImportFiles
{
    /** @return string a temporary .xlsx */
    public function template(string $kind): string
    {
        $fields = Importers::for($kind)->fields();
        $examples = __('import::examples.'.$kind);

        return $this->write(__('import::import.kinds.'.$kind.'.title'), [
            array_map(fn ($f) => __('import::fields.'.$kind.'.'.$f->key).($f->required ? ' *' : ''), $fields),
            ...array_map(fn (array $example) => array_map(fn ($f) => (string) ($example[$f->key] ?? ''), $fields), is_array($examples) ? $examples : []),
        ]);
    }

    /** @return string a temporary .xlsx of the rows with a problem, or null when there are none */
    public function problems(ImportRun $run): ?string
    {
        $rows = $run->rows()->whereIn('status', ['error', 'failed'])->get();
        if ($rows->isEmpty()) {
            return null;
        }

        return $this->write(__('import::import.problems.sheet'), [
            [__('import::import.problems.line'), __('import::import.problems.reason'), ...$run->headers],
            ...$rows->map(fn ($row) => [$row->line, (string) $row->message, ...array_map(fn ($i) => (string) ($row->cells[$i] ?? ''), array_keys($run->headers))])->all(),
        ]);
    }

    public function templateName(string $kind): string
    {
        return 'import-'.$kind.'.xlsx';
    }

    public function problemsName(ImportRun $run): string
    {
        return 'import-problems-'.pathinfo($run->file_name, PATHINFO_FILENAME).'.xlsx';
    }

    /** @param  list<list<string|int>>  $rows  the first is the titles */
    private function write(string $sheetName, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import-').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName(mb_substr($sheetName, 0, 31));
        $sheet->setSheetView((new SheetView)->setRightToLeft(str_starts_with(app()->getLocale(), 'ar')));
        $sheet->setColumnWidthForRange(22, 1, max(1, count($rows[0] ?? [])));
        foreach ($rows as $i => $row) {
            $writer->addRow(Row::fromValues($row, $i === 0 ? (new Style)->setFontBold()->setBackgroundColor('EEF2F7') : null));
        }
        $writer->close();

        return $path;
    }
}
