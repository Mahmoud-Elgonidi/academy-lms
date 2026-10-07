<?php

namespace Modules\Import\Importers;

use Modules\Import\Support\Field;
use Modules\Import\Support\ImportContext;
use Modules\Import\Support\RowOutcome;

/**
 * One kind of sheet (teachers, families, schedules): the columns it reads,
 * and what a row makes. A row that cannot be imported throws a
 * ValidationException saying why; the caller keeps each row in its own
 * savepoint, so one bad row never spoils the rest.
 */
interface Importer
{
    public function kind(): string;

    /** @return list<Field> */
    public function fields(): array;

    /** @param  array<string, string>  $row  field key => the cell, trimmed ("" when empty or not mapped) */
    public function import(array $row, ImportContext $context): RowOutcome;
}
