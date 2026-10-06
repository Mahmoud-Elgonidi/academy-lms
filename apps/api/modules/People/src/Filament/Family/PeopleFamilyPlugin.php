<?php

namespace Modules\People\Filament\Family;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

/** People in the family area: whose account the page shows, and switching between children. */
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
    }

    public function boot(Panel $panel): void {}
}
