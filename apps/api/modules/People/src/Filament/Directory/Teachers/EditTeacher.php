<?php

namespace Modules\People\Filament\Directory\Teachers;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\People\Actions\Directory\UpdateTeacher;
use Modules\People\Filament\Directory\DirectoryForm;
use Modules\People\Queries\Models\Person;

final class EditTeacher extends EditRecord
{
    protected static string $resource = TeacherResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Person $teacher */
        $teacher = $this->getRecord()->load('teacherProfile');

        return TeacherResource::fill($teacher);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        DirectoryForm::attempt(fn () => app(UpdateTeacher::class)->handle($record->getKey(), DirectoryForm::details($data), TeacherResource::profile($data)));

        return Person::query()->findOrFail($record->getKey());
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('people::directory.saved');
    }

    protected function getHeaderActions(): array
    {
        return DirectoryForm::accountActions(fn () => Person::query()->findOrFail($this->getRecord()->getKey()), fn () => $this->refreshFormData([]));
    }
}
