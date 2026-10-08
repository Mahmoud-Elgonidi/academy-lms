<?php

namespace Modules\Learning\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Modules\Learning\Filament\Pages\ReportsOversight;
use Modules\Learning\Filament\Pages\ReportTemplates;

/**
 * Learning in the academy back office: report follow-up, and the curriculum
 * and progress section of the student account (Scheduling's page renders
 * the "scheduling::enrollment.account" hook).
 */
final class LearningPlugin implements Plugin
{
    public const ACCOUNT_HOOK = 'scheduling::enrollment.account';

    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'learning';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([ReportsOversight::class, ReportTemplates::class]);
    }

    public function boot(Panel $panel): void {}

    /** Called by LearningServiceProvider: the account section, whichever panel boots first. */
    public static function registerAccountSection(): void
    {
        Livewire::component('learning.enrollment', EnrollmentLearning::class);
        FilamentView::registerRenderHook(self::ACCOUNT_HOOK, fn (array $data) => Blade::render(
            '@livewire(\'learning.enrollment\', [\'enrollmentId\' => $id], key(\'learning-\'.$id))',
            ['id' => $data['enrollmentId']],
        ));
    }
}
