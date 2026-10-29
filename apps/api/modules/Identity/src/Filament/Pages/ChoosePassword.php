<?php

namespace Modules\Identity\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Modules\Identity\Actions\SetFirstPassword;
use Modules\Identity\Actions\SetOwnPassword;
use Modules\Identity\Models\User;
use Modules\Identity\TwoFactor\TwoFactor;
use Modules\SharedKernel\Audit\AuditActor;

/**
 * "Choose a password" (no menu entry): for someone signed in who has never
 * chosen one (they came in by a one-time code or a sign-in link). Two-step
 * sign-in is set up against the password, so the back office sends whoever
 * must set it up here first (RequireTwoFactor), then on to the set-up; My
 * settings sends a passwordless person here before two-step sign-in or a
 * password change. Saves through SetOwnPassword.
 *
 * @property-read Schema $form
 */
final class ChoosePassword extends Page
{
    protected string $view = 'identity::security.choose-password';

    protected static ?string $slug = 'choose-password';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    /** Back to My settings afterwards (from there), else on to wherever they were going. */
    #[Locked]
    public bool $toSettings = false;

    /** This page is the way to two-step sign-in, so it never asks for it first. */
    public static function isMultiFactorAuthenticationRequired(Panel $panel): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return Filament::auth()->user() instanceof User;
    }

    public function getTitle(): string|Htmlable
    {
        return __('identity::security.password.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __($this->twoFactorNext() ? 'identity::security.password.subtitle' : 'identity::security.password.subtitle_plain');
    }

    public function mount(): void
    {
        $this->toSettings = request()->query('back') === 'settings';
        if ($this->user()->password !== null || AuditActor::current()->onBehalfOf !== null) {
            $this->redirect($this->next());

            return;
        }
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('password')
                ->label(__('identity::security.password.password'))
                ->helperText(__('identity::security.password.hint', ['min' => SetFirstPassword::MIN]))
                ->password()->revealable()
                ->required()->minLength(SetFirstPassword::MIN)->maxLength(255)
                ->autocomplete('new-password'),
            TextInput::make('password_confirmation')
                ->label(__('identity::security.password.confirm'))
                ->password()->revealable()
                ->required()->same('password')
                ->validationMessages(['same' => __('identity::welcome.errors.confirm')])
                ->autocomplete('new-password')
                ->dehydrated(false),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        try {
            $user = app(SetOwnPassword::class)->handle($this->user(), (string) $state['password']);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['data.password' => collect($e->errors())->flatten()->all()]);
        }
        // The session keeps the password's hash (AuthenticateSession): the new one, so it stays signed in.
        if (request()->hasSession()) {
            request()->session()->put('password_hash_'.Filament::getAuthGuard(), $user->getAuthPassword());
        }
        Notification::make()->success()->title(__('identity::security.password.saved'))->send();
        $this->redirect($this->next());
    }

    /** Whether two-step sign-in must be set up next. */
    private function twoFactorNext(): bool
    {
        $user = $this->user();

        return TwoFactor::requiredFor($user) && ! TwoFactor::enabledFor($user) && AuditActor::current()->onBehalfOf === null
            && Filament::getCurrentOrDefaultPanel()->isMultiFactorAuthenticationRequired();
    }

    /** Two-step sign-in's set-up when it is asked of them, else My settings (from there) or where they were going. */
    private function next(): string
    {
        if ($this->twoFactorNext()) {
            return (string) Filament::getSetUpRequiredMultiFactorAuthenticationUrl();
        }
        if ($this->toSettings) {
            return (string) Filament::getProfileUrl();
        }

        return (string) session()->pull('url.intended', Filament::getUrl());
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
