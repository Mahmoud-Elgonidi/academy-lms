<?php

namespace Modules\Catalog\Actions;

use Modules\Catalog\Models\Subject;

/** A subject by its names alone (an import that meets one the academy does not have). */
final class CreateSubject
{
    public function __construct(private readonly SaveSubject $save) {}

    public function handle(string $nameAr, string $nameEn): Subject
    {
        return $this->save->handle(null, $nameAr, $nameEn);
    }
}
