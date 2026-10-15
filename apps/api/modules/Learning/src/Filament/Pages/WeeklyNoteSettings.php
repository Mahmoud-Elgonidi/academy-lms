<?php

namespace Modules\Learning\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Modules\Learning\Actions\Digest\SaveWeeklyDigest;
use Modules\Learning\Models\LearningSettings;
use Modules\Learning\Models\WeeklyDigest;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Authorization\Ability;
use UnitEnum;

/**
 * Settings › Weekly note (owner and admin): the weekly note to guardians on
 * or off, what it holds and never holds, and how it is worded now.
 *
 * @property-read Schema $form
 */
final class WeeklyNoteSettings extends Page
{
    protected string $view = 'learning::filament.weekly-note';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static ?int $navigationSort = 87;

    protected static ?string $slug = 'settings/weekly-note';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageTemplates->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('platform::branding.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('learning::digest.settings.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('learning::digest.settings.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('learning::digest.settings.subtitle');
    }

    public function mount(): void
    {
        $this->form->fill(['on' => LearningSettings::current()->weekly_digest]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Toggle::make('on')->label(__('learning::digest.settings.toggle'))->helperText(__('learning::digest.settings.when')),
        ]);
    }

    /** Whether the AI helper words the note now (else the facts as they are). */
    public function worded(): bool
    {
        return app(AcademyAi::class)->on(AiFeature::DigestWording);
    }

    /** Notes that went out in the last seven days. */
    public function lastWeek(): int
    {
        return WeeklyDigest::query()->where('status', 'sent')->where('sent_at', '>=', CarbonImmutable::now()->subDays(7))->count();
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        app(SaveWeeklyDigest::class)->handle((bool) ($this->form->getState()['on'] ?? false));
        Notification::make()->success()->title(__('learning::digest.settings.saved'))->send();
    }
}
