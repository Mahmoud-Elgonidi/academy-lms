<?php

namespace Modules\People\Filament\Directory\Teachers;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\People\Actions\Directory\AddTeacher;
use Modules\People\Filament\Directory\DirectoryForm;
use Modules\People\Queries\Models\Person;

final class CreateTeacher extends CreateRecord
{
    protected static string $resource = TeacherResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        $id = DirectoryForm::attempt(fn () => app(AddTeacher::class)->handle(DirectoryForm::details($data), TeacherResource::profile($data), (bool) ($data['welcome'] ?? false)));

        return Person::query()->findOrFail($id);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('people::directory.teachers.created');
    }

    protected function getRedirectUrl(): string
    {
        return TeacherResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
