<?php

namespace Modules\Payroll\Filament\Teacher;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** Payroll in the teacher area: their own earnings. */
final class PayrollTeacherPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'payroll-teacher';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([MyEarnings::class]);
    }

    public function boot(Panel $panel): void {}
}
