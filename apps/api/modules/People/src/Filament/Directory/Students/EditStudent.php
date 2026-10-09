<?php

namespace Modules\People\Filament\Directory\Students;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\People\Actions\Directory\UpdateStudent;
use Modules\People\Filament\Directory\DirectoryForm;
use Modules\People\Queries\Models\Person;

final class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Person $student */
        $student = $this->getRecord()->load('studentProfile');

        return StudentResource::fill($student);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Person $record */
        DirectoryForm::attempt(function () use ($record, $data) {
            $who = DirectoryForm::details($data);
            if ($record->is_managed_minor) {
                // A child under their guardian keeps no ways in of their own.
                $who = new UserDetails($who->name, null, null, $who->locale, $who->timezone);
            }
            app(UpdateStudent::class)->handle($record->id, $who, StudentResource::birthDate($data), StudentResource::stage($data), StudentResource::details($data));
        });

        return Person::query()->findOrFail($record->getKey());
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('people::directory.saved');
    }

    protected function getHeaderActions(): array
    {
        return [
            DirectoryForm::calendarAction('student', fn () => (string) $this->getRecord()->getKey()),
            ...DirectoryForm::accountActions(fn () => Person::query()->findOrFail($this->getRecord()->getKey()), fn () => $this->refreshFormData([])),
        ];
    }
}
