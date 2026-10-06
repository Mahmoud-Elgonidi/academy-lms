<?php

namespace Modules\Catalog;

use Modules\Catalog\Actions\CreateSubject;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Catalog\Models\Subject;

final class CatalogService implements CatalogApi
{
    public function __construct(private readonly CreateSubject $createSubject) {}

    public function createSubject(string $nameAr, string $nameEn): string
    {
        return $this->createSubject->handle($nameAr, $nameEn)->id;
    }

    public function subjectNames(): array
    {
        $column = app()->getLocale() === 'ar' ? 'name_ar' : 'name_en';

        return Subject::query()->orderBy($column)->pluck($column, 'id')->all();
    }
}
