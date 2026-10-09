<?php

namespace Modules\People\Filament\Directory\Students;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

final class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('people::directory.students.subtitle');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('people::directory.students.create'))];
    }
}
