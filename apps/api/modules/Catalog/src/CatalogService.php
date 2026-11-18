<?php

namespace Modules\Catalog;

use Modules\Catalog\Actions\CreateSubject;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Catalog\Models\Subject;
use Modules\Catalog\Models\SubjectCategory;
use Modules\Catalog\Models\SubjectLevel;

final class CatalogService implements CatalogApi
{
    public function __construct(private readonly CreateSubject $createSubject) {}

    public function createSubject(string $nameAr, string $nameEn): string
    {
        return $this->createSubject->handle($nameAr, $nameEn)->id;
    }

    public function subjectNames(): array
    {
        $column = $this->column();

        return Subject::query()->orderBy($column)->pluck($column, 'id')->all();
    }

    public function levels(string $subjectId): array
    {
        return SubjectLevel::query()->where('subject_id', $subjectId)->orderBy('ordinal')->pluck($this->column(), 'id')->all();
    }

    public function categories(string $subjectId): array
    {
        return SubjectCategory::query()->where('subject_id', $subjectId)->orderBy('ordinal')->pluck($this->column(), 'id')->all();
    }

    private function column(): string
    {
        return app()->getLocale() === 'ar' ? 'name_ar' : 'name_en';
    }
}
