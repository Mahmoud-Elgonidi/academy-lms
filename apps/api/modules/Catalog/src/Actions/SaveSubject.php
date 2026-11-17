<?php

namespace Modules\Catalog\Actions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Models\Subject;
use Modules\Catalog\Models\SubjectCategory;
use Modules\Catalog\Models\SubjectLevel;

/**
 * A subject with its levels in order and its categories (tracks or school
 * curricula). Items keep their ids, so a renamed or moved level stays the
 * one teachers and students point at; one still in use cannot go (the
 * database holds it) and the academy is told who uses it.
 */
final class SaveSubject
{
    public const MAX_LEVELS = 30;

    public const MAX_CATEGORIES = 20;

    /**
     * @param  list<array{id?: ?string, name_ar: string, name_en?: ?string}>  $levels  in order
     * @param  list<array{id?: ?string, name_ar: string, name_en?: ?string}>  $categories  in order
     */
    public function handle(?string $subjectId, string $nameAr, ?string $nameEn, array $levels = [], array $categories = []): Subject
    {
        $nameAr = trim($nameAr);
        $nameEn = trim((string) $nameEn) ?: $nameAr;
        $e = 'catalog::subjects.errors.';
        if ($nameAr === '' || mb_strlen($nameAr) > 120 || mb_strlen($nameEn) > 120) {
            throw ValidationException::withMessages(['name_ar' => __($e.'name')]);
        }
        $taken = Subject::query()
            ->when($subjectId, fn ($q, $id) => $q->whereKeyNot($id))
            ->where(fn ($q) => $q->whereRaw('lower(name_ar) = lower(?)', [$nameAr])->orWhereRaw('lower(name_en) = lower(?)', [$nameEn]))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['name_ar' => __($e.'taken', ['name' => $nameAr])]);
        }
        $levels = $this->items($levels, self::MAX_LEVELS, 'levels');
        $categories = $this->items($categories, self::MAX_CATEGORIES, 'categories');

        return DB::transaction(function () use ($subjectId, $nameAr, $nameEn, $levels, $categories) {
            $subject = $subjectId === null ? new Subject : Subject::query()->lockForUpdate()->findOrFail($subjectId);
            $subject->fill(['name_ar' => $nameAr, 'name_en' => $nameEn])->save();

            $this->sync($subject, SubjectLevel::class, $levels, 'levels');
            $this->sync($subject, SubjectCategory::class, $categories, 'categories');

            return $subject->load(['levels', 'categories']);
        });
    }

    /**
     * @param  list<array{id?: ?string, name_ar: string, name_en?: ?string}>  $items
     * @return list<array{id: ?string, name_ar: string, name_en: string}>
     */
    private function items(array $items, int $max, string $field): array
    {
        $e = 'catalog::subjects.errors.';
        $clean = [];
        foreach ($items as $item) {
            $ar = trim((string) ($item['name_ar'] ?? ''));
            $en = trim((string) ($item['name_en'] ?? '')) ?: $ar;
            if ($ar === '' && $en === '') {
                continue;
            }
            if ($ar === '' || mb_strlen($ar) > 80 || mb_strlen($en) > 80) {
                throw ValidationException::withMessages([$field => __($e.'item_name')]);
            }
            $clean[] = ['id' => ($item['id'] ?? null) ?: null, 'name_ar' => $ar, 'name_en' => $en];
        }
        if (count($clean) > $max) {
            throw ValidationException::withMessages([$field => __($e.$field.'_max', ['max' => $max])]);
        }
        $names = array_map(fn (array $i) => Str::lower($i['name_ar']), $clean);
        if (count($names) !== count(array_unique($names))) {
            throw ValidationException::withMessages([$field => __($e.'item_twice')]);
        }

        return $clean;
    }

    /**
     * @param  class-string<SubjectLevel|SubjectCategory>  $model
     * @param  list<array{id: ?string, name_ar: string, name_en: string}>  $items
     */
    private function sync(Subject $subject, string $model, array $items, string $field): void
    {
        $existing = $model::query()->where('subject_id', $subject->id)->get()->keyBy('id');
        $kept = array_filter(array_column($items, 'id'), fn (?string $id) => $id !== null && $existing->has($id));

        foreach ($existing->except($kept) as $gone) {
            try {
                DB::transaction(fn () => $gone->delete());
            } catch (QueryException $e) {
                if ($e->getCode() !== '23503') {
                    throw $e;
                }
                throw ValidationException::withMessages([$field => __("catalog::subjects.errors.{$field}_in_use", ['name' => $gone->name()])]);
            }
        }

        foreach ($items as $i => $item) {
            $row = $item['id'] !== null && $existing->has($item['id']) ? $existing[$item['id']] : new $model(['subject_id' => $subject->id]);
            $row->fill(['ordinal' => $i + 1, 'name_ar' => $item['name_ar'], 'name_en' => $item['name_en']])->save();
        }
    }
}
