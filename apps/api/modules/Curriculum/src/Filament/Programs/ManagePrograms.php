<?php

namespace Modules\Curriculum\Filament\Programs;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Curriculum\Actions\SaveProgram;

final class ManagePrograms extends ManageRecords
{
    protected static string $resource = ProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('curriculum::curriculum.add_program'))
                ->using(fn (array $data) => app(SaveProgram::class)->handle(null, $data))
                ->successRedirectUrl(fn ($record) => EditCurriculum::getUrl(['record' => $record])),
        ];
    }
}
