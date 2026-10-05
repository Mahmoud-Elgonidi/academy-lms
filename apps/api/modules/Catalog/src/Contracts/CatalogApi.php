<?php

namespace Modules\Catalog\Contracts;

/** What other modules may ask of Catalog. */
interface CatalogApi
{
    /** Creates a subject in the current academy and returns its id. */
    public function createSubject(string $nameAr, string $nameEn): string;
}
