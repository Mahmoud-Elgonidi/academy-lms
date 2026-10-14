<?php

namespace App\Filament\Academy;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupChecklist;
use Modules\SharedKernel\Setup\SetupProgress;
use Modules\SharedKernel\Setup\SetupStep;
use UnitEnum;

/**
 * "Start here" (docs/architecture/02, "قايمة تجهيز الأكاديمية"): what a new
 * academy gets ready before its first families come, from the steps each
 * module registers with SharedKernel's SetupChecklist (this page knows no
 * module). The required steps make the progress; the optional ones wait
 * apart. While something required is left it heads the menu with the count
 * and the owner and admins land here on signing in (OpenFirstAllowedScreen);
 * then it moves down to Settings, still there.
 */
final class StartHere extends Page
{
    protected string $view = 'academy.start-here';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $slug = 'start-here';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::SetUpAcademy->value);
    }

    /** Where the academy stands, read once per request: the menu's place, its badge, the landing and the page all ask. */
    public static function progress(): SetupProgress
    {
        $request = request();
        $progress = $request->attributes->get(self::class);
        if (! $progress instanceof SetupProgress) {
            $request->attributes->set(self::class, $progress = app(SetupChecklist::class)->progress());
        }

        return $progress;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return self::progress()->complete() ? __('platform::branding.nav_group') : null;
    }

    public static function getNavigationSort(): ?int
    {
        return self::progress()->complete() ? 99 : -100;
    }

    public static function getNavigationBadge(): ?string
    {
        $left = self::progress()->requiredLeft();

        return $left > 0 ? (string) $left : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationLabel(): string
    {
        return __('setup.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('setup.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('setup.subtitle');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $progress = self::progress();
        $row = fn (SetupStep $step): array => [
            'key' => $step->key(),
            'title' => $step->title(),
            'hint' => $step->hint(),
            'done' => $progress->isDone($step),
            'url' => $step->url(),
            // A step the viewer may not do is shown greyed, with who does it in words, never hidden: it still counts.
            'allowed' => $step->ability() === null || Gate::allows($step->ability()->value),
        ];

        $groups = [];
        foreach ($progress->required() as $step) {
            $groups[$step->group()->value] ??= ['key' => $step->group()->value, 'label' => $step->group()->label(), 'steps' => []];
            $groups[$step->group()->value]['steps'][] = $row($step);
        }

        return [
            'groups' => array_values($groups),
            'optional' => array_map($row, $progress->optional()),
            'done' => $progress->requiredDone(),
            'total' => count($progress->required()),
            'left' => $progress->requiredLeft(),
            'percent' => $progress->percent(),
        ];
    }
}
