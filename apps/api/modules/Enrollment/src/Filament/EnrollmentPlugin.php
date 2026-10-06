<?php

namespace Modules\Enrollment\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Enrollment\Filament\Resources\Enrollments\EnrollmentResource;

/** Enrollment's screens in the academy back office. */
final class EnrollmentPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'enrollment';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([EnrollmentResource::class]);
    }

    public function boot(Panel $panel): void {}
}
