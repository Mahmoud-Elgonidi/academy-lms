<?php

namespace Modules\Import\Actions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Import\Importers\Importers;
use Modules\Import\Models\ImportRun;
use Modules\Import\Support\ImportContext;
use Modules\Import\Support\RowOutcome;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Goes through a run's rows in order, each in its own savepoint, so a row
 * that fails leaves nothing behind and the rows after it still see what
 * the rows before made (a guardian two siblings share).
 */
final class RowsRunner
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** @return array<string, array{0: ?RowOutcome, 1: ?string}> row id => the outcome, or why it failed */
    public function run(ImportRun $run, string $actorId): array
    {
        $importer = Importers::for($run->kind);
        $tenant = $this->tenant->require();
        $context = new ImportContext((string) $run->option('calling_code', '20'), $tenant->defaultTimezone, $tenant->defaultLocale, $actorId);

        $results = [];
        foreach ($run->rows()->get() as $row) {
            try {
                $results[$row->id] = [DB::transaction(fn () => $importer->import($row->values($run->mapping), $context)), null];
            } catch (ValidationException $e) {
                $results[$row->id] = [null, (string) collect($e->errors())->flatten()->first()];
            } catch (QueryException|InvalidArgumentException $e) {
                report($e);
                $results[$row->id] = [null, __('import::import.errors.database')];
            }
        }

        return $results;
    }
}
