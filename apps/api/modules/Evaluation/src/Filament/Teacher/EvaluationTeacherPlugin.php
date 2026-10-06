<?php

namespace Modules\Evaluation\Filament\Teacher;

use Filament\Contracts\Plugin;
use Filament\Panel;

/** Evaluation in the teacher area: their own performance. */
final class EvaluationTeacherPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'evaluation-teacher';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([MyPerformance::class]);
    }

    public function boot(Panel $panel): void {}
}
