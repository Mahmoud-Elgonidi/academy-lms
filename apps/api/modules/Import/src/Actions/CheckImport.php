<?php

namespace Modules\Import\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Import\Models\ImportRow;
use Modules\Import\Models\ImportRun;

/**
 * A dry run: the whole file is imported in one transaction that is then
 * rolled back, so each row says exactly what the import would do with it
 * (new, update, skip, or the problem), rows that depend on earlier ones
 * included. Messages that would go out are never sent.
 */
final class CheckImport
{
    private const STATUS = ['created' => 'new', 'updated' => 'update', 'skipped' => 'skip'];

    public function __construct(private readonly RowsRunner $runner) {}

    public function handle(ImportRun $run, string $actorId): ImportRun
    {
        if ($run->status === 'done') {
            throw ValidationException::withMessages(['run' => __('import::import.errors.done')]);
        }

        DB::beginTransaction();
        try {
            $results = $this->runner->run($run, $actorId);
        } finally {
            DB::rollBack();
        }

        return DB::transaction(function () use ($run, $results) {
            $counts = array_fill_keys(ImportRow::CHECKED, 0);
            foreach ($results as $rowId => [$outcome, $error]) {
                $status = $outcome ? self::STATUS[$outcome->status] : 'error';
                $counts[$status]++;
                ImportRow::query()->whereKey($rowId)->update(['status' => $status, 'message' => $error ?? implode(' · ', $outcome->notes), 'refs' => '{}']);
            }
            $run->forceFill(['status' => 'checked', 'counts' => $counts, 'checked_at' => CarbonImmutable::now()])->save();

            return $run;
        });
    }
}
