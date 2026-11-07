<?php

namespace Modules\Platform\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Modules\Platform\Actions\Modules\SaveAcademyModules;
use Modules\Platform\Models\AcademyModules;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Plans\PlatformAcademyPlan;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Filament\NavGroup;
use Modules\SharedKernel\Plans\Feature;
use UnitEnum;

/**
 * Settings › Academy modules (docs/architecture/02, "وحدات الأكاديمية"): the
 * core that is always on (safeguarding and privacy never off), the optional
 * areas the owner and admins switch, each after a confirmation that says
 * where it disappears, what goes with it, what stays and what must be done
 * first, and the plan's areas with where to get those that are not in it.
 * Menus follow on the next page the person opens.
 */
final class AcademyModulesPage extends Page
{
    protected string $view = 'platform::filament.academy-modules';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 900;

    protected static ?string $slug = 'settings/modules';

    /** The core, as people know it; safeguarding and privacy are never switched off. */
    public const CORE = ['scheduling', 'people', 'classroom', 'billing', 'reports', 'family', 'notifications', 'team', 'safeguarding', 'privacy'];

    public const NEVER_OFF = ['safeguarding', 'privacy'];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageModules->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return NavGroup::Settings;
    }

    public static function getNavigationLabel(): string
    {
        return __('platform::modules.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::modules.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::modules.subtitle');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $plan = app(PlatformAcademyPlan::class);
        $hidden = AcademyModules::current()->hiddenFeatures();
        $s = 'platform::modules.state.';

        $optional = array_map(function (Feature $f) use ($plan, $hidden, $s): array {
            $needs = array_values(array_filter($f->dependsOn(), fn (Feature $n) => ! $plan->has($n)));
            $state = match (true) {
                ! $plan->planHas($f) => 'platform_off',
                in_array($f, $hidden, true) => 'off',
                $needs !== [] => 'needs',
                default => 'on',
            };

            return [
                'key' => $f->value,
                'label' => $f->label(),
                'line' => $f->description(),
                'state' => $state,
                'note' => match ($state) {
                    'platform_off' => __($s.'platform_off'),
                    'needs' => __($s.'needs', ['needs' => $needs[0]->label()]),
                    default => null,
                },
                // Off by the academy, or on: the academy switches it; the platform team's switch is theirs.
                'switchable' => $state !== 'platform_off',
                'on' => $state === 'on',
                'hidden' => in_array($f, $hidden, true),
            ];
        }, Feature::optional());

        $plans = SaasPlan::query()->whereNull('archived_at')->orderBy('sort')->get(['name_ar', 'name_en', 'features']);
        $paid = array_map(function (Feature $f) use ($plan, $plans, $s): array {
            $on = $plan->has($f);
            $in = $plans->filter(fn (SaasPlan $p) => in_array($f->value, (array) $p->features, true))
                ->map(fn (SaasPlan $p) => app()->getLocale() === 'ar' ? $p->name_ar : $p->name_en)->values()->all();

            return [
                'key' => $f->value,
                'label' => $f->label(),
                'line' => $f->description(),
                'on' => $on,
                'note' => match (true) {
                    $on => __($s.'in_plan'),
                    $in !== [] => __($s.'available_in', ['plans' => implode(app()->getLocale() === 'ar' ? ' أو ' : ' or ', $in)]),
                    default => __($s.'on_request'),
                },
            ];
        }, Feature::paid());

        $core = array_map(fn (string $key): array => [
            'key' => $key,
            'label' => __('platform::modules.core.'.$key.'.label'),
            'line' => __('platform::modules.core.'.$key.'.line'),
            'never_off' => in_array($key, self::NEVER_OFF, true),
        ], self::CORE);

        return ['core' => $core, 'optional' => $optional, 'paid' => $paid];
    }

    /** Switching one optional area, after a confirmation with what it does (SaveAcademyModules::preview). */
    public function toggleAction(): Action
    {
        $feature = fn (array $arguments): Feature => Feature::from((string) ($arguments['feature'] ?? ''));
        $turnOn = fn (array $arguments): bool => (bool) ($arguments['on'] ?? false);
        $preview = fn (array $arguments): array => app(SaveAcademyModules::class)->preview($feature($arguments), $turnOn($arguments));

        return Action::make('toggle')
            ->label(fn (array $arguments) => __($turnOn($arguments) ? 'platform::modules.actions.turn_on' : 'platform::modules.actions.turn_off'))
            ->color(fn (array $arguments) => $turnOn($arguments) ? 'primary' : 'gray')
            ->size('sm')
            ->requiresConfirmation()
            ->modalIcon(fn (array $arguments) => $turnOn($arguments) ? Heroicon::OutlinedPlayCircle : Heroicon::OutlinedPauseCircle)
            ->modalIconColor(fn (array $arguments) => $turnOn($arguments) ? 'primary' : 'warning')
            ->modalHeading(fn (array $arguments) => __($turnOn($arguments) ? 'platform::modules.actions.confirm_on' : 'platform::modules.actions.confirm_off', ['module' => $feature($arguments)->label()]))
            ->modalDescription(null)
            ->modalContent(fn (array $arguments) => view('platform::filament.academy-modules-preview', ['preview' => $preview($arguments)]))
            ->modalSubmitActionLabel(fn (array $arguments) => __($turnOn($arguments) ? 'platform::modules.actions.turn_on' : 'platform::modules.actions.turn_off'))
            ->modalSubmitAction(fn (Action $submit, array $arguments) => $preview($arguments)['refusals'] === [] ? $submit : false)
            ->visible(fn () => self::canAccess())
            ->action(function (array $arguments) use ($feature, $turnOn): void {
                $module = $feature($arguments);
                $on = $turnOn($arguments);
                app(SaveAcademyModules::class)->handle([$module->value => $on], (string) auth()->id());
                Notification::make()->success()->title(__($on ? 'platform::modules.saved_on' : 'platform::modules.saved_off', ['module' => $module->label()]))->send();
                // The menu is drawn with the page: open it again so it follows at once.
                $this->redirect(self::getUrl(), navigate: true);
            });
    }
}
