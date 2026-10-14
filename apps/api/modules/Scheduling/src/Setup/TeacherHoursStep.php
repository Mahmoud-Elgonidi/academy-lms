<?php

namespace Modules\Scheduling\Setup;

use Illuminate\Support\Facades\Gate;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupGroup;
use Modules\SharedKernel\Setup\SetupStep;

/**
 * An active teacher with weekly hours in force or coming (an approved shift
 * schedule with working blocks): without one, AvailabilityQuery finds no
 * time and nothing can be booked.
 */
final class TeacherHoursStep implements SetupStep
{
    public function key(): string
    {
        return 'scheduling.teacher_hours';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Teaching;
    }

    public function order(): int
    {
        return 30;
    }

    public function required(): bool
    {
        return true;
    }

    public function title(): string
    {
        return __('scheduling::setup.hours.title');
    }

    public function hint(): string
    {
        return __('scheduling::setup.hours.hint');
    }

    public function done(): bool
    {
        return ShiftSchedule::query()
            ->where('status', 'active')
            ->whereRaw('(upper_inf(effective) or upper(effective) > current_date)')
            ->whereHas('rules', fn ($q) => $q->where('kind', 'work'))
            ->whereExists(fn ($q) => $q->from('users')->whereColumn('users.academy_id', 'teacher_shift_schedules.academy_id')
                ->whereColumn('users.id', 'teacher_shift_schedules.teacher_id')->where('users.status', 'active'))
            ->exists();
    }

    public function url(): ?string
    {
        return Gate::allows(Ability::ReviewShifts->value) ? route('filament.academy.pages.shifts.proposals') : null;
    }

    public function ability(): ?Ability
    {
        return Ability::ReviewShifts;
    }
}
