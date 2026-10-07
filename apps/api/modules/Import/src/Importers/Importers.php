<?php

namespace Modules\Import\Importers;

use InvalidArgumentException;

/** The kinds of sheet an academy can bring in, in the order they make sense: teachers, then families, then schedules. */
final class Importers
{
    public const KINDS = ['teachers' => TeachersImporter::class, 'families' => FamiliesImporter::class, 'schedules' => SchedulesImporter::class];

    public static function for(string $kind): Importer
    {
        $class = self::KINDS[$kind] ?? throw new InvalidArgumentException("Unknown import [{$kind}].");

        return app($class);
    }
}
