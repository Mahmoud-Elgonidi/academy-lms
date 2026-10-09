<?php

namespace Modules\People\Filament\Directory\Teachers;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

final class ListTeachers extends ListRecords
{
    protected static string $resource = TeacherResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('people::directory.teachers.subtitle');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('people::directory.teachers.create'))];
    }
}
