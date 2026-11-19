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

        // A subject without an English name reads in Arabic on the English page rather than vanishing.
        $names = [];
        foreach (Subject::query()->orderByRaw("coalesce(nullif({$column}, ''), name_ar)")->get(['id', 'name_ar', 'name_en']) as $subject) {
            $names[(string) $subject->id] = (string) ($subject->{$column} ?: $subject->name_ar);
        }

        return $names;
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
