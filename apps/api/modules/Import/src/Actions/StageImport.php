<?php

namespace Modules\Import\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Import\Importers\Importers;
use Modules\Import\Models\ImportRun;
use Modules\Import\Support\Places;
use Modules\Import\Support\SheetReader;
use Modules\SharedKernel\Tenancy\TenantContext;
use Throwable;

/**
 * Takes a file in: its rows are kept (the first is the column titles), and
 * its columns are matched to what the importer reads by their titles, in
 * Arabic or English. Nothing is imported yet.
 */
final class StageImport
{
    public const MAX_ROWS = 2000;

    public function __construct(
        private readonly SheetReader $reader,
        private readonly TenantContext $tenant,
    ) {}

    public function handle(string $kind, string $path, string $fileName, string $actorId): ImportRun
    {
        $e = 'import::import.errors.';
        if (! isset(Importers::KINDS[$kind])) {
            throw ValidationException::withMessages(['kind' => __($e.'kind')]);
        }
        if (! preg_match('/\.(xlsx|csv|txt)$/i', $fileName)) {
            throw ValidationException::withMessages(['file' => __($e.'type')]);
        }
        try {
            $rows = $this->reader->read($path, $fileName, self::MAX_ROWS + 1);
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => __($e.'unreadable')]);
        }
        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file' => __($e.'empty')]);
        }
        if (count($rows) > self::MAX_ROWS + 1) {
            throw ValidationException::withMessages(['file' => __($e.'too_many', ['max' => self::MAX_ROWS])]);
        }

        $headers = array_map('trim', array_shift($rows));
        $width = max(count($headers), ...array_map('count', $rows));
        $headers = array_pad($headers, $width, '');

        return DB::transaction(function () use ($kind, $fileName, $actorId, $headers, $rows) {
            $run = ImportRun::query()->create([
                'kind' => $kind,
                'file_name' => mb_substr(basename($fileName), 0, 200),
                'headers' => $headers,
                'mapping' => self::guess($kind, $headers),
                'options' => ['calling_code' => Places::callingCode($this->tenant->require()->defaultTimezone), 'invite' => true],
                'created_by' => $actorId,
            ]);
            foreach ($rows as $i => $cells) {
                $run->rows()->create(['line' => $i + 2, 'cells' => array_pad($cells, count($headers), '')]);
            }

            return $run;
        });
    }

    /**
     * Each field to the first column whose title it goes by.
     *
     * @param  list<string>  $headers
     * @return array<string, int|null>
     */
    public static function guess(string $kind, array $headers): array
    {
        $mapping = [];
        $used = [];
        foreach (Importers::for($kind)->fields() as $field) {
            $mapping[$field->key] = null;
            foreach ($headers as $i => $header) {
                if (! in_array($i, $used, true) && $field->matches($header)) {
                    $mapping[$field->key] = $i;
                    $used[] = $i;
                    break;
                }
            }
        }

        return $mapping;
    }
}
