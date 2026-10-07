<?php

namespace Modules\Import\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Import\Importers\Importers;
use Modules\Import\Models\ImportRun;
use Modules\SharedKernel\Contact\Phone;

/** Says which column holds what, and the run's options; a run changed this way is checked again before it imports. */
final class MapImportColumns
{
    /**
     * @param  array<string, int|string|null>  $mapping  field => column index
     * @param  array{calling_code?: string, invite?: bool}  $options
     */
    public function handle(ImportRun $run, array $mapping, array $options = []): ImportRun
    {
        $e = 'import::import.errors.';
        if ($run->status === 'done') {
            throw ValidationException::withMessages(['run' => __($e.'done')]);
        }
        $clean = [];
        foreach (Importers::for($run->kind)->fields() as $field) {
            $column = $mapping[$field->key] ?? null;
            $column = $column === null || $column === '' ? null : (int) $column;
            if ($column !== null && ! array_key_exists($column, $run->headers)) {
                throw ValidationException::withMessages(['mapping' => __($e.'column')]);
            }
            if ($column === null && $field->required) {
                throw ValidationException::withMessages(['mapping.'.$field->key => __($e.'required', ['field' => __('import::fields.'.$run->kind.'.'.$field->key)])]);
            }
            $clean[$field->key] = $column;
        }
        $code = preg_replace('/\D/', '', (string) ($options['calling_code'] ?? $run->option('calling_code', '')));
        if ($code === '' || Phone::normalize('1234567', $code) === null) {
            throw ValidationException::withMessages(['calling_code' => __($e.'calling_code')]);
        }

        $run->forceFill([
            'mapping' => $clean,
            'options' => ['calling_code' => $code, 'invite' => (bool) ($options['invite'] ?? $run->option('invite', true))],
            'status' => 'staged',
        ])->save();

        return $run;
    }
}
