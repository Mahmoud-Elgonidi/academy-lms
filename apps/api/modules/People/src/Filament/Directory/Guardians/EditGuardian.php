<?php

namespace Modules\People\Filament\Directory\Guardians;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Contracts\IdentityApi;
use Modules\People\Filament\Directory\DirectoryForm;
use Modules\People\Queries\Models\Person;

final class EditGuardian extends EditRecord
{
    protected static string $resource = GuardianResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Person $guardian */
        $guardian = $this->getRecord();

        return DirectoryForm::fill($guardian);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        DirectoryForm::attempt(fn () => app(IdentityApi::class)->updateUser($record->getKey(), DirectoryForm::details($data)));

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
