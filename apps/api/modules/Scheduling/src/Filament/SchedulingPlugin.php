<?php

namespace Modules\Scheduling\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Scheduling\Filament\Pages\AcademyCalendar;
use Modules\Scheduling\Filament\Pages\DstChanges;
use Modules\Scheduling\Filament\Pages\FindTeacher;
use Modules\Scheduling\Filament\Pages\Holidays;
use Modules\Scheduling\Filament\Pages\LessonDisputes;
use Modules\Scheduling\Filament\Pages\LiveBoard;
use Modules\Scheduling\Filament\Pages\PolicySettings;
use Modules\Scheduling\Filament\Pages\ReviewShiftProposal;
use Modules\Scheduling\Filament\Pages\ShiftProposals;
use Modules\Scheduling\Filament\Pages\TimeOffRequests;
use Modules\Scheduling\Filament\Pages\TimeOffReview;
use Modules\Scheduling\Filament\Resources\Enrollments\EnrollmentResource;

/** Scheduling's screens in the academy back office. */
final class SchedulingPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'scheduling';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([LiveBoard::class, AcademyCalendar::class, FindTeacher::class, ShiftProposals::class, ReviewShiftProposal::class, TimeOffRequests::class, TimeOffReview::class, LessonDisputes::class, DstChanges::class, Holidays::class, PolicySettings::class]);
        $panel->resources([EnrollmentResource::class]);
    }

    public function boot(Panel $panel): void {}
}
