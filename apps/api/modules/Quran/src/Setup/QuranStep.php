<?php

namespace Modules\Quran\Setup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Quran\Filament\Pages\QuranPrograms;
use Modules\Quran\Support\QuranArea;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\ConditionalSetupStep;
use Modules\SharedKernel\Setup\SetupGroup;

/**
 * The Quran set up (docs/architecture/02, section 26), on the checklist only
 * while the academy's plan has the Quran: a subject of the Quran kind and at
 * least one program, so its courses have portions. Required once a Quran
 * subject exists (its courses wait for a program); optional before.
 */
final class QuranStep implements ConditionalSetupStep
{
    /** @var ?array{subject: bool, program: bool} read once, in one query, for this reading of the checklist */
    private ?array $facts = null;

    public function __construct(private readonly QuranArea $area) {}

    public function applies(): bool
    {
        return $this->area->on();
    }

    public function key(): string
    {
        return 'quran.programs';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Teaching;
    }

    public function order(): int
    {
        return 25;
    }

    public function required(): bool
    {
        return $this->facts()['subject'];
    }

    public function title(): string
    {
        return __('quran::screens.setup.title');
    }

    public function hint(): string
    {
        return __('quran::screens.setup.hint');
    }

    public function done(): bool
    {
        return $this->facts()['subject'] && $this->facts()['program'];
    }

    /** @return array{subject: bool, program: bool} whether the academy has a Quran subject, and a program not archived */
    private function facts(): array
    {
        if ($this->facts === null) {
            $row = DB::selectOne("select exists (select 1 from subjects where kind = 'quran') as subject, exists (select 1 from quran_programs where archived_at is null) as program");
            $this->facts = ['subject' => (bool) $row->subject, 'program' => (bool) $row->program];
        }

        return $this->facts;
    }

    public function url(): ?string
    {
        return Gate::allows(Ability::ManageQuran->value) ? QuranPrograms::getUrl(panel: 'academy') : null;
    }

    public function ability(): ?Ability
    {
        return Ability::ManageQuran;
    }
}
