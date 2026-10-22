<?php

namespace Modules\Identity\Filament\Pages;

use DateTimeZone;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\ChangePassword;
use Modules\Identity\Actions\Contact\ConfirmContactChange;
use Modules\Identity\Actions\Contact\RequestContactChange;
use Modules\Identity\Actions\Social\UnlinkSocialIdentity;
use Modules\Identity\Actions\UpdatePreferences;
use Modules\Identity\Actions\UpdateProfile;
use Modules\Identity\Contracts\Preferences\PreferenceDefinition;
use Modules\Identity\Models\Impersonation;
use Modules\Identity\Models\User;
use Modules\Identity\Preferences\PreferenceStore;
use Modules\Identity\Queries\SocialAccounts;
use Modules\Identity\Social\Provider;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Time\TimeFormat;
use Modules\SharedKernel\Time\ViewerClock;
use Modules\SharedKernel\Time\ViewerTimezone;
use SensitiveParameter;

/**
 * "My settings": name, language, timezone, the email and mobile number (a
 * new one after a code sent to it), the screen choices modules declare (e.g.
 * the live board layout), password, Google and Apple sign-in (link through
 * the provider, unlink here), and two-step sign-in (Filament's authenticator
 * app section). Saves through the same Actions the API uses.
 */
final class MySettings extends EditProfile
{
    /** The time format choice that follows the academy's (kept as null). */
    private const ACADEMY_CLOCK = 'academy';

    private ?string $localeBeforeSave = null;

    /** @var list<array{admin: string, when: string, minutes: int, reason: string}>|null */
    private ?array $signIns = null;

    /** @var array{challenge: string, kind: string, sent_to: string, channel: string}|null the new email or number waiting for its code */
    public ?array $contactPending = null;

    /** Back from linking Google or Apple (SocialSignInController::finish). */
    public function mount(): void
    {
        parent::mount();
        if (is_string($done = session()->pull('identity.social.done'))) {
            Notification::make()->title($done)->success()->send();
        }
        if (is_string($error = session()->pull('identity.social.error'))) {
            Notification::make()->title($error)->danger()->persistent()->send();
        }
    }

    public static function getLabel(): string
    {
        return __('identity::settings.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('identity::settings.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('identity::settings.sections.account'))
                ->schema([$this->getNameFormComponent()]),

            Section::make(__('identity::settings.sections.region'))
                ->description(__('identity::settings.region_hint'))
                ->schema([
                    Select::make('locale')
                        ->label(__('identity::settings.fields.locale'))
                        ->options(['ar' => 'العربية', 'en' => 'English'])
                        ->required()
                        ->native(false),
                    Select::make('timezone')
                        ->label(__('identity::settings.fields.timezone'))
                        ->options(fn (): array => self::timezoneOptions())
                        ->searchable()
                        ->required()
                        ->helperText(__('identity::settings.timezone_hint')),
                    // How times are written for this person; "the academy's" follows the academy's choice.
                    ToggleButtons::make('time_format')
                        ->label(__('identity::settings.fields.time_format'))
                        ->options(fn (): array => [
                            self::ACADEMY_CLOCK => __('identity::settings.time_format.academy', ['format' => __('identity::settings.time_format.'.app(ViewerClock::class)->academy()->value)]),
                            TimeFormat::Twelve->value => __('identity::settings.time_format.h12'),
                            TimeFormat::TwentyFour->value => __('identity::settings.time_format.h24'),
                        ])
                        ->inline()
                        ->required(),
                ]),

            Section::make(__('identity::settings.contact.title'))
                ->description(__('identity::settings.contact.hint'))
                ->schema([View::make('identity::settings.contact')->viewData(fn (): array => [
                    ...$this->contactRows(),
                ])])
                // Not while the platform team is signed in as this person (RequestContactChange refuses too).
                ->visible(fn (): bool => AuditActor::current()->onBehalfOf === null),

            Section::make(__('identity::settings.sections.screens'))
                ->schema($this->preferenceComponents())
                ->visible(fn (): bool => $this->offered() !== []),

            Section::make(__('identity::settings.sections.password'))
                ->schema([
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                    $this->getCurrentPasswordFormComponent(),
                ])
                // Not while the platform team is signed in as this person (ChangePassword refuses too).
                ->visible(fn (): bool => AuditActor::current()->onBehalfOf === null),

            Section::make(__('identity::social.settings.title'))
                ->description(__('identity::social.settings.description'))
                ->schema([View::make('identity::social.linked')->viewData(fn (): array => ['rows' => $this->socialRows(), 'panel' => Filament::getCurrentOrDefaultPanel()->getId()])])
                // Not while the platform team is signed in as this person (linking refuses too).
                ->visible(fn (): bool => Provider::forWeb() !== [] && AuditActor::current()->onBehalfOf === null),

            Section::make(__('identity::impersonation.mine.title'))
                ->description(__('identity::impersonation.mine.hint'))
                ->schema([View::make('identity::impersonation.mine')->viewData(['rows' => $this->platformSignIns()])])
                ->visible(fn (): bool => $this->platformSignIns() !== [])
                ->collapsible(),
        ]);
    }

    /** Step one of a new email or number: where to send the code. */
    public function changeContactAction(): Action
    {
        $l = 'identity::settings.contact.';
        $kind = fn (array $arguments): string => ($arguments['kind'] ?? '') === 'phone' ? 'phone' : 'email';
        // Whether there is one to change, or one to add (the row says).
        $has = fn (array $arguments): bool => (bool) ($arguments['has'] ?? true);

        return Action::make('changeContact')
            ->label(fn (array $arguments): string => __($l.($has($arguments) ? 'change_' : 'add_').$kind($arguments)))
            ->color('gray')->outlined()->size('sm')
            ->modalHeading(fn (array $arguments): string => __($l.($has($arguments) ? 'change_' : 'add_').$kind($arguments)))
            ->modalWidth('md')
            ->modalSubmitActionLabel(__($l.'send'))
            ->schema(fn (array $arguments): array => [
                TextInput::make('value')->label(__($l.'new_'.$kind($arguments)))->required()->maxLength(255)
                    ->type($kind($arguments) === 'email' ? 'email' : 'tel')
                    ->helperText($kind($arguments) === 'phone' ? __($l.'phone_hint') : null)
                    ->extraInputAttributes(['dir' => 'ltr', 'autocomplete' => $kind($arguments) === 'email' ? 'email' : 'tel']),
            ])
            ->action(function (array $arguments, array $data, Action $action) use ($kind): void {
                try {
                    $sent = app(RequestContactChange::class)->handle($this->getUser(), $kind($arguments), (string) $data['value'], null, request()->ip());
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title((string) collect($e->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                }
                $this->contactPending = ['challenge' => $sent->challengeId, 'kind' => $kind($arguments), 'sent_to' => $sent->sentTo, 'channel' => $sent->channel];
                $this->replaceMountedAction('confirmContact');
            });
    }

    /** Step two: the code that came to the new address makes it this person's. */
    public function confirmContactAction(): Action
    {
        $l = 'identity::settings.contact.';

        return Action::make('confirmContact')
            ->modalHeading(fn (): string => __($l.'change_'.($this->contactPending['kind'] ?? 'email')))
            ->modalDescription(fn (): string => __($l.'code_hint', [
                'contact' => $this->contactPending['sent_to'] ?? '',
                'channel' => __($l.'channels.'.($this->contactPending['channel'] ?? 'email')),
                'minutes' => (int) config('identity.otp.minutes'),
            ]))
            ->modalWidth('md')
            ->modalSubmitActionLabel(__($l.'confirm'))
            ->schema([
                TextInput::make('code')->label(__($l.'code'))->required()->maxLength(12)
                    ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric', 'autocomplete' => 'one-time-code']),
            ])
            ->action(function (array $data, Action $action) use ($l): void {
                $pending = $this->contactPending;
                if ($pending === null) {
                    return;
                }
                try {
                    app(ConfirmContactChange::class)->handle($this->getUser(), $pending['challenge'], (string) $data['code']);
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title((string) collect($e->errors())->flatten()->first())->send();
                    $action->halt();

                    return;
                }
                $this->contactPending = null;
                Notification::make()->success()->title(__($l.'done_'.$pending['kind']))->send();
                $this->redirect(self::getUrl(), navigate: true);
            });
    }

    /** @return array{rows: list<array{kind: string, label: string, value: ?string}>, managed: bool} as stored now (just changed, it shows) */
    private function contactRows(): array
    {
        $now = User::query()->whereKey($this->getUser()->getAuthIdentifier())->first(['email', 'phone_e164', 'is_managed_minor']);

        return [
            'rows' => [
                ['kind' => 'email', 'label' => __('identity::settings.contact.email'), 'value' => $now?->email],
                ['kind' => 'phone', 'label' => __('identity::settings.contact.phone'), 'value' => $now?->phone_e164],
            ],
            'managed' => (bool) $now?->is_managed_minor,
        ];
    }

    /** Unlink Google or Apple, after a word on what it means. */
    public function unlinkSocialAction(): Action
    {
        $provider = fn (array $arguments): Provider => Provider::tryFrom((string) ($arguments['provider'] ?? '')) ?? Provider::Google;

        return Action::make('unlinkSocial')
            ->label(__('identity::social.settings.unlink'))
            ->color('danger')
            ->outlined()
            ->size('sm')
            ->requiresConfirmation()
            ->modalSubmitActionLabel(__('identity::social.settings.unlink'))
            ->modalHeading(fn (array $arguments): string => __('identity::social.settings.unlink').' · '.$provider($arguments)->label())
            ->modalDescription(fn (array $arguments): string => __('identity::social.settings.unlink_confirm', ['provider' => $provider($arguments)->label()]))
            ->action(function (array $arguments) use ($provider): void {
                /** @var User $user */
                $user = $this->getUser();
                try {
                    app(UnlinkSocialIdentity::class)->handle($user, $provider($arguments));
                } catch (ValidationException $e) {
                    Notification::make()->title((string) collect($e->errors())->flatten()->first())->danger()->send();

                    return;
                }
                Notification::make()->title(__('identity::social.settings.unlinked_done', ['provider' => $provider($arguments)->label()]))->success()->send();
            });
    }

    /**
     * Each provider on for the web, and whether this account is linked to it.
     *
     * @return list<array{provider: string, label: string, email: ?string, last_used: ?string}>
     */
    private function socialRows(): array
    {
        /** @var User $user */
        $user = $this->getUser();
        $tz = app(ViewerTimezone::class)->effective();

        return array_map(fn (array $row) => [
            'provider' => $row['provider']->value,
            'label' => $row['provider']->label(),
            'linked' => $row['linked'],
            'email' => $row['email'],
            'last_used' => $row['last_used_at']?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y'),
        ], app(SocialAccounts::class)->of($user));
    }

    /** Two-step sign-in (set up, new recovery codes, turn off), never while the platform team is signed in as this person. */
    public function getMultiFactorAuthenticationContentComponent(): ?Component
    {
        return AuditActor::current()->onBehalfOf === null ? parent::getMultiFactorAuthenticationContentComponent() : null;
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getUser();

        foreach ($this->offered() as $key => $definition) {
            $data[self::field($key)] = $this->store()->valueFor($user, $key);
        }
        $data['time_format'] = TimeFormat::of(data_get($user, 'time_format'))?->value ?? self::ACADEMY_CLOCK;

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        /** @var User $record */
        $this->localeBeforeSave = $record->locale;

        app(UpdateProfile::class)->handle($record, Arr::only($data, ['name', 'locale', 'timezone', 'time_format']));

        $preferences = [];
        foreach (array_keys($this->offered()) as $key) {
            if (array_key_exists(self::field($key), $data)) {
                $preferences[$key] = $data[self::field($key)];
            }
        }
        app(UpdatePreferences::class)->handle($record, $preferences);

        if (filled($data['password'] ?? null)) {
            app(ChangePassword::class)->handle($record, $data['password']);
        }

        return $record;
    }

    /** A new language flips the layout direction, so reload the page. */
    protected function getRedirectUrl(): ?string
    {
        return $this->localeBeforeSave !== $this->getUser()->getAttribute('locale') ? self::getUrl() : null;
    }

    /**
     * The platform team's sign-ins to this account that were used, newest first.
     *
     * @return list<array{admin: string, when: string, minutes: int, reason: string}>
     */
    private function platformSignIns(): array
    {
        if ($this->signIns !== null) {
            return $this->signIns;
        }
        $tz = app(ViewerTimezone::class)->effective();

        return $this->signIns = Impersonation::query()
            ->where('user_id', $this->getUser()->getAuthIdentifier())
            ->whereNotNull('redeemed_at')
            ->latest('redeemed_at')->limit(10)->get()
            ->map(fn (Impersonation $i) => [
                'admin' => $i->admin_name,
                'when' => $i->redeemed_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y · H:i'),
                'minutes' => $i->minutes,
                'reason' => $i->reason,
            ])->all();
    }

    /** @return list<ToggleButtons> */
    private function preferenceComponents(): array
    {
        $components = [];

        foreach ($this->offered() as $key => $definition) {
            $components[] = ToggleButtons::make(self::field($key))
                ->label(__($definition->label))
                ->helperText($definition->hint ? __($definition->hint) : null)
                ->options(array_map(fn (string $label) => __($label), $definition->options))
                ->inline()
                ->required();
        }

        return $components;
    }

    /** @return array<string, PreferenceDefinition> the choices this person is offered */
    private function offered(): array
    {
        return array_filter($this->store()->definitions(), fn (PreferenceDefinition $d): bool => $d->offered());
    }

    /** Form field names cannot hold dots, which Filament reads as nesting. */
    private static function field(string $key): string
    {
        return 'pref__'.str_replace('.', '__', $key);
    }

    /** @return array<string, array<string, string>> IANA names grouped by region, labelled with today's offset. */
    private static function timezoneOptions(): array
    {
        $groups = [];
        $now = new \DateTimeImmutable('now');

        foreach (DateTimeZone::listIdentifiers() as $id) {
            $offset = (new DateTimeZone($id))->getOffset($now);
            $sign = $offset < 0 ? '−' : '+';
            $label = sprintf('%s (UTC%s%02d:%02d)', str_replace('_', ' ', $id), $sign, intdiv(abs($offset), 3600), intdiv(abs($offset) % 3600, 60));
            $groups[strstr($id, '/', true) ?: $id][$id] = $label;
        }

        return $groups;
    }

    private function store(): PreferenceStore
    {
        return app(PreferenceStore::class);
    }
}
