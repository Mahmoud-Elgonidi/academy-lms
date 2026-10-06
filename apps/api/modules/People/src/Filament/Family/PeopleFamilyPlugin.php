<?php

namespace Modules\People\Filament\Family;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;

/**
 * People in the family area: whose account the page shows, switching
 * between children, and the look of a student's own area by their stage
 * (LearnerStage): the stage's font and shapes, and a tab bar on phones.
 */
final class PeopleFamilyPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function getId(): string
    {
        return 'people-family';
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(PanelsRenderHook::PAGE_START, fn () => auth()->check() ? Blade::render('@livewire(\'people.learner-switcher\')') : '');
        $panel->renderHook(PanelsRenderHook::BODY_START, fn () => ($stage = self::stage()) ? view('people::family.stage', ['stage' => $stage->value]) : '');
        $panel->renderHook(PanelsRenderHook::BODY_END, fn () => self::stage() ? view('people::family.tabs') : '');
    }

    public function boot(Panel $panel): void {}

    /** The signed-in student's stage, or null for the classic look (guardians, adults, the login page). */
    private static function stage(): ?LearnerStage
    {
        $stage = auth()->check() ? app(FamilyView::class)->stage((string) auth()->id()) : LearnerStage::Classic;

        return $stage === LearnerStage::Classic ? null : $stage;
    }
}
