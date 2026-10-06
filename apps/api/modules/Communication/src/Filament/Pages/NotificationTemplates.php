<?php

namespace Modules\Communication\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification as Toast;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Modules\Communication\Actions\SaveCommunicationSettings;
use Modules\Communication\Actions\SaveTemplateWording;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Models\CommunicationSettings;
use Modules\Communication\Models\TemplateWording;
use Modules\Communication\Templates\Renderer;
use Modules\Communication\Templates\TemplateRegistry;
use Modules\SharedKernel\Authorization\Ability;
use UnitEnum;

/**
 * The wording of every message, in Arabic and English, with the academy's
 * own changes; and which channels the academy sends on.
 */
final class NotificationTemplates extends Page
{
    protected string $view = 'communication::filament.templates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?int $navigationSort = 61;

    protected static ?string $slug = 'messages/templates';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageTemplates->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('communication::communication.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('communication::communication.templates.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('communication::communication.templates.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('communication::communication.templates.subtitle');
    }

    /** @return array<string, list<array<string, mixed>>> by category */
    public function groups(): array
    {
        $renderer = app(Renderer::class);
        $renderer->forget();
        $edited = TemplateWording::query()->get(['template_key', 'locale'])->groupBy('template_key');
        $locale = app()->getLocale();
        $groups = array_fill_keys(TemplateDefinition::CATEGORIES, []);

        foreach (app(TemplateRegistry::class)->all() as $t) {
            $preview = $renderer->render($t, $locale, $t->sample, 'UTC');
            $groups[$t->category][] = [
                'key' => $t->key,
                'audience' => __($t->audience),
                'channels' => $t->channels,
                'title' => $preview->title,
                'body' => $preview->body,
                'edited' => $edited->has($t->key),
            ];
        }

        return array_filter($groups);
    }

    protected function getHeaderActions(): array
    {
        $l = 'communication::communication.templates.';

        return [
            Action::make('channels')
                ->label(__($l.'settings'))->icon(Heroicon::OutlinedAdjustmentsHorizontal)->color('gray')
                ->modalDescription(__($l.'settings_hint'))
                ->fillForm(fn () => CommunicationSettings::current()->only(['email_enabled', 'whatsapp_enabled', 'push_enabled']))
                ->schema([
                    Toggle::make('whatsapp_enabled')->label(__($l.'whatsapp_enabled')),
                    Toggle::make('push_enabled')->label(__($l.'push_enabled')),
                    Toggle::make('email_enabled')->label(__($l.'email_enabled')),
                ])
                ->action(function (array $data) use ($l) {
                    app(SaveCommunicationSettings::class)->handle($data);
                    Toast::make()->success()->title(__($l.'settings_saved'))->send();
                }),
        ];
    }

    public function editAction(): Action
    {
        $l = 'communication::communication.templates.';

        return Action::make('edit')
            ->label(__($l.'edit'))->icon(Heroicon::OutlinedPencilSquare)->link()->size('sm')
            ->modalHeading(fn (array $arguments) => $this->template($arguments['key'])->key)
            ->fillForm(function (array $arguments) {
                $t = $this->template($arguments['key']);
                [$titleAr, $bodyAr] = app(Renderer::class)->wording($t, 'ar');
                [$titleEn, $bodyEn] = app(Renderer::class)->wording($t, 'en');

                return ['title_ar' => $titleAr, 'body_ar' => $bodyAr, 'title_en' => $titleEn, 'body_en' => $bodyEn];
            })
            ->schema(fn (array $arguments) => [
                Text::make(__($l.'placeholders', ['list' => implode('  ', array_map(fn ($p) => ":{$p}", $this->template($arguments['key'])->placeholders())) ?: '—'])),
                Grid::make(2)->schema([
                    TextInput::make('title_ar')->label(__($l.'title_ar'))->maxLength(200),
                    TextInput::make('title_en')->label(__($l.'title_en'))->maxLength(200),
                    Textarea::make('body_ar')->label(__($l.'body_ar'))->rows(3),
                    Textarea::make('body_en')->label(__($l.'body_en'))->rows(3),
                ]),
            ])
            ->extraModalFooterActions(fn (Action $action) => [
                $action->makeModalSubmitAction('reset', arguments: ['reset' => true])->label(__($l.'reset'))->color('gray'),
            ])
            ->action(function (array $data, array $arguments) use ($l) {
                $t = $this->template($arguments['key']);
                $save = app(SaveTemplateWording::class);
                foreach (['ar', 'en'] as $locale) {
                    $reset = ($arguments['reset'] ?? false) === true;
                    [$defaultTitle, $defaultBody] = [(string) trans()->get($t->title, [], $locale), (string) trans()->get($t->body, [], $locale)];
                    $title = trim((string) $data["title_{$locale}"]);
                    $body = trim((string) $data["body_{$locale}"]);
                    // Unchanged from the module's wording: keep no override, so later improvements reach this academy.
                    $same = $title === $defaultTitle && $body === $defaultBody;
                    $save->handle($t->key, $locale, $reset || $same ? null : $title, $reset || $same ? null : $body, (string) auth()->id());
                }
                Toast::make()->success()->title(__($l.'saved'))->send();
            });
    }

    private function template(string $key): TemplateDefinition
    {
        return app(TemplateRegistry::class)->get($key);
    }
}
