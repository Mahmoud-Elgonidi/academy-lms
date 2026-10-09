<?php

namespace Modules\People\Filament\Directory\Students;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\People\Actions\Directory\AddStudent;
use Modules\People\Filament\Directory\DirectoryForm;
use Modules\People\Queries\Models\Person;

final class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        $id = DirectoryForm::attempt(function () use ($data) {
            $who = DirectoryForm::details($data);
            if (! ($data['own_contact'] ?? false)) {
                // A child under their guardian: no ways in of their own.
                $who = new UserDetails($who->name, null, null, $who->locale, $who->timezone);
            }

            return app(AddStudent::class)->handle($who, StudentResource::guardian($data), StudentResource::birthDate($data),
                StudentResource::stage($data), StudentResource::details($data), (bool) ($data['welcome'] ?? false));
        });

        return Person::query()->findOrFail($id);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('people::directory.students.created');
    }

    protected function getRedirectUrl(): string
    {
        return StudentResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
