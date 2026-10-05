<?php

namespace Modules\Catalog\Actions;

use Modules\Catalog\Models\Subject;

final class CreateSubject
{
    public function handle(string $nameAr, string $nameEn): Subject
    {
        return Subject::query()->create(['name_ar' => trim($nameAr), 'name_en' => trim($nameEn)]);
    }
}
