<?php

namespace Modules\Import\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Import\Contracts\Events\PeopleImported;
use Modules\Import\Models\ImportRow;
use Modules\Import\Models\ImportRun;

/**
 * Imports a checked file: every row in its own savepoint, so the rows that
 * can be imported are, and each one that cannot says why; the run as a
 * whole commits once. New accounts are welcomed afterwards when the run
 * asks for it. A run imports once.
 */
final class RunImport
{
    public function __construct(private readonly RowsRunner $runner) {}

    public function handle(ImportRun $run, string $actorId): ImportRun
    {
        if ($run->status !== 'checked') {
            throw ValidationException::withMessages(['run' => __($run->status === 'done' ? 'import::import.errors.done' : 'import::import.errors.check_first')]);
        }

        return DB::transaction(function () use ($run, $actorId) {
            $run = ImportRun::query()->lockForUpdate()->findOrFail($run->id);
            if ($run->status !== 'checked') {
                throw ValidationException::withMessages(['run' => __('import::import.errors.done')]);
            }

            $counts = array_fill_keys(ImportRow::DONE, 0);
            $people = [];
            foreach ($this->runner->run($run, $actorId) as $rowId => [$outcome, $error]) {
                $status = $outcome?->status ?? 'failed';
                $counts[$status]++;
                $people = [...$people, ...($outcome->newPeople ?? [])];
                ImportRow::query()->whereKey($rowId)->update([
                    'status' => $status,
                    'message' => $error ?? implode(' · ', $outcome->notes),
                    'refs' => json_encode($outcome->refs ?? []),
                ]);
            }
            $run->forceFill(['status' => 'done', 'counts' => $counts, 'finished_at' => CarbonImmutable::now()])->save();

            $people = array_values(array_unique($people));
            if ($people !== []) {
                DB::afterCommit(fn () => event(new PeopleImported($run->academy_id, $run->id, $run->kind, $people, (bool) $run->option('invite', true))));
            }

            return $run;
        });
    }
}
