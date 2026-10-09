<?php

namespace Modules\Catalog\Contracts;

/** What other modules may ask of Catalog. */
interface CatalogApi
{
    /** Creates a subject in the current academy and returns its id. */
    public function createSubject(string $nameAr, string $nameEn): string;

    /** @return array<string, string> subject id => name in the current locale, by name */
    public function subjectNames(): array;

    /** @return array<string, string> level id => name in the current locale, in the subject's order */
    public function levels(string $subjectId): array;

    /** @return array<string, string> category id => name in the current locale (the subject's tracks or curricula), in order */
    public function categories(string $subjectId): array;
}
