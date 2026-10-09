<?php

namespace Modules\People\Filament\Directory\Guardians;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\People\Actions\Directory\AddGuardian;
use Modules\People\Filament\Directory\DirectoryForm;
use Modules\People\Queries\Models\Person;

final class CreateGuardian extends CreateRecord
{
    protected static string $resource = GuardianResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        $id = DirectoryForm::attempt(fn () => app(AddGuardian::class)->handle(DirectoryForm::details($data), (bool) ($data['welcome'] ?? false)));

        return Person::query()->findOrFail($id);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('people::directory.guardians.created');
    }

    protected function getRedirectUrl(): string
    {
        return GuardianResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
