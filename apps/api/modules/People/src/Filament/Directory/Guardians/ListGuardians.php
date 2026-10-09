<?php

namespace Modules\People\Filament\Directory\Guardians;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

final class ListGuardians extends ListRecords
{
    protected static string $resource = GuardianResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('people::directory.guardians.subtitle');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('people::directory.guardians.create'))];
    }
}
