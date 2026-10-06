<?php

namespace Modules\Evaluation\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Evaluation\Filament\Pages\TeacherPerformance;
use Modules\Evaluation\Filament\Pages\TeachersPerformance;

/** Evaluation in the academy back office: the teachers' performance, and each teacher's page. */
final class EvaluationPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'evaluation';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([TeachersPerformance::class, TeacherPerformance::class]);
    }

    public function boot(Panel $panel): void {}
}
