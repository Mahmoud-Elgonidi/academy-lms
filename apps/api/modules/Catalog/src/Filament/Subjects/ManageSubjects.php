<?php

namespace Modules\Catalog\Filament\Subjects;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

final class ManageSubjects extends ManageRecords
{
    protected static string $resource = SubjectResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('catalog::subjects.subtitle');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('catalog::subjects.create'))
                ->modalWidth('3xl')
                ->using(fn (array $data) => SubjectResource::attempt(fn () => SubjectResource::save(null, $data)))
                ->successNotificationTitle(__('catalog::subjects.saved')),
        ];
    }
}
