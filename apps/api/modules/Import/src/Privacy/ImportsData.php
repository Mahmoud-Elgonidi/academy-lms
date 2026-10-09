<?php

namespace Modules\Import\Privacy;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * The spreadsheet rows a person was brought in from: their cells are a copy
 * of what their account already holds, so a copy of the person leaves them
 * out, and erasing empties them (the row keeps its line and outcome).
 */
final class ImportsData implements PersonalDataSource
{
    public function key(): string
    {
        return 'imports';
    }

    public function export(string $userId): PersonalDataPart
    {
        return new PersonalDataPart([]);
    }

    public function blockers(string $userId): array
    {
        return [];
    }

    public function erase(string $userId): array
    {
        return ['imported_rows' => DB::table('import_rows')
            ->whereRaw('exists (select 1 from jsonb_each_text(refs) r where r.value = ?)', [$userId])
            ->where('cells', '!=', '{}')
            ->update(['cells' => '{}', 'updated_at' => now()])];
    }
}
