<?php

namespace Modules\Identity\Filament\Pages;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Modules\Identity\Accounts\AccountLookup;
use Modules\Identity\Actions\Otp\RequestSignInCode;
use Modules\Identity\Actions\Otp\VerifySignInCode;
use Modules\Identity\Contracts\SignInGate;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Social\Provider;
use SensitiveParameter;
use Throwable;

/**
 * Sign-in for every panel with what people have: an email, a mobile number
 * (as they write it: "+20 101…", "00966…", or local "0101…" when only one
 * account here ends with it), or a username, and a password; or, instead of
 * the password, a one-time code sent by WhatsApp, text or email. Whoever has
 * two-step sign-in on is then asked for the code from their authenticator
 * app (or a recovery code), by password (Filament's own challenge) or by
 * code alike.
 */
final class SignIn extends Login
{
    /** password | code */
    #[Locked]
    public string $mode = 'password';

    /** The code step: what was sent, and where to send a new one. */
    #[Locked]
    public ?string $challengeId = null;

    #[Locked]
    public ?string $contact = null;

    #[Locked]
    public ?string $sentTo = null;

    #[Locked]
    public ?string $channel = null;

    /** @var list<string> */
    #[Locked]
    public array $channels = [];

    /** After a right one-time code, whose second step this is and until when (encrypted: [user id, unix time]). */
    #[Locked]
    public ?string $codePassed = null;

    public function mount(): void
    {
        parent::mount();
        $this->finishSocialSignIn();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent()->visible(fn (): bool => $this->mode === 'password'),
            TextInput::make('code')
                ->label(__('identity::otp.sign_in.code'))
                ->required()
                ->maxLength(12)
                ->autocomplete('one-time-code')
                ->inputMode('numeric')
                ->extraInputAttributes(['dir' => 'ltr', 'class' => 'lms-otp-input'])
                // WebOTP: on phones that support it, the code from the text fills in by itself.
                ->extraAlpineAttributes(['x-init' => "if ('OTPCredential' in window) { navigator.credentials.get({ otp: { transport: ['sms'] } }).then((otp) => { if (otp) { \$wire.set('data.code', otp.code) } }).catch(() => {}) }"])
                ->visible(fn (): bool => $this->inCodeStep()),
            $this->getRememberFormComponent()->visible(fn (): bool => $this->mode === 'password' || $this->inCodeStep()),
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(fn (): string => $this->mode === 'code' ? __('identity::otp.sign_in.contact') : __('identity::auth.sign_in.login'))
            ->required()
            ->maxLength(255)
            ->autocomplete('username')
            ->autofocus()
            ->visible(fn (): bool => ! $this->inCodeStep());
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')->label(__('filament-panels::auth/pages/login.form.remember.label'));
    }

    public function authenticate(): ?LoginResponse
    {
        if ($this->mode !== 'code') {
            return parent::authenticate();
        }
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return $this->afterCodeSecondStep();
        }

        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        if (! $this->inCodeStep()) {
            $this->sendCode((string) $this->form->getState()['email']);

            return null;
        }

        $data = $this->form->getState();
        try {
            $user = app(VerifySignInCode::class)->handle((string) $this->challengeId, (string) $data['code']);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['data.code' => $e->errors()['code'] ?? [__('identity::otp.expired')]]);
        }

        // The code was right, but not for this door (a parent at /admin), or not yet (a child waiting for a guardian): start again.
        try {
            $allowed = $this->isUserAllowedToAccessPanel($user);
        } catch (ValidationException $held) {
            $this->reset('challengeId', 'sentTo', 'channel', 'channels');
            throw $held;
        }
        if (! $allowed) {
            $this->reset('challengeId', 'sentTo', 'channel', 'channels');
            throw ValidationException::withMessages(['data.email' => __('filament-panels::auth/pages/login.messages.failed')]);
        }

        // Two-step sign-in on: the code proved the phone or the email; the authenticator app comes next.
        $challenge = $this->getMultiFactorChallenge();
        if ($provider = $challenge->getFirstEnabledProvider($user)) {
            $this->userUndertakingMultiFactorAuthentication = encrypt($user->getAuthIdentifier());
            $this->codePassed = encrypt([$user->getAuthIdentifier(), now()->addMinutes(10)->getTimestamp()]);
            $challenge->beforeChallenge($user, $provider);
            $this->multiFactorChallengeForm->fill();

            return null;
        }

        Filament::auth()->login($user, (bool) ($data['remember'] ?? false));
        session()->regenerate();

        return app(LoginResponse::class);
    }

    /**
     * Asked once the password or the code is right. Someone the sign-in gate
     * holds back (a child whose guardian has not agreed yet) hears why,
     * instead of "these credentials do not match".
     */
    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        if (parent::isUserAllowedToAccessPanel($user)) {
            return true;
        }
        if ($user instanceof User && $user->isActive() && $user->hasRole(Role::Student, Role::Guardian) && Filament::getCurrentOrDefaultPanel()->getId() === 'family'
            && ($refusal = app(SignInGate::class)->refusal($user->id))) {
            $this->userUndertakingMultiFactorAuthentication = null;
            throw ValidationException::withMessages(['data.email' => $refusal->message]);
        }

        return false;
    }

    /** The authenticator app's code after a right one-time code, within ten minutes of it. */
    private function afterCodeSecondStep(): ?LoginResponse
    {
        $user = $this->getUserUndertakingMultiFactorAuthentication();
        try {
            [$passedFor, $until] = decrypt((string) $this->codePassed);
        } catch (Throwable) {
            $passedFor = $until = null;
        }
        if ($user === null || $passedFor !== $user->getAuthIdentifier() || $until < now()->getTimestamp() || ! $this->isUserAllowedToAccessPanel($user)) {
            $this->reset('userUndertakingMultiFactorAuthentication', 'codePassed', 'challengeId', 'sentTo', 'channel', 'channels');
            throw ValidationException::withMessages(['data.email' => __('identity::two_factor.errors.start_again')]);
        }
        if ($this->isMultiFactorChallengeRateLimited($user)) {
            return null;
        }
        $this->multiFactorChallengeForm->validate();

        Filament::auth()->login($user, (bool) ($this->data['remember'] ?? false));
        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE),
            $this->getFormContentComponent(),
            $this->getMultiFactorChallengeFormContentComponent(),
            Actions::make([$this->useCodeAction(), $this->resendAction(), $this->resendByAction(), $this->changeContactAction(), $this->usePasswordAction()])
                ->alignment(Alignment::Center)
                ->key('sign-in-ways')
                ->visible(fn (): bool => blank($this->userUndertakingMultiFactorAuthentication)),
            View::make('identity::social.buttons')
                ->viewData(['providers' => Provider::forWeb(), 'panel' => Filament::getCurrentOrDefaultPanel()->getId()])
                ->visible(fn (): bool => Provider::forWeb() !== [] && blank($this->userUndertakingMultiFactorAuthentication) && ! $this->inCodeStep()),
            View::make('identity::sign-in.legal')->visible(fn (): bool => Route::has('privacy.notice') && Route::has('privacy.terms')),
            RenderHook::make(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER),
        ]);
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()->label(fn (): string => match (true) {
            $this->mode === 'password' => __('filament-panels::auth/pages/login.form.actions.authenticate.label'),
            $this->inCodeStep() => __('identity::otp.sign_in.verify'),
            default => __('identity::otp.sign_in.send'),
        });
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->inCodeStep()) {
            return __('identity::otp.sign_in.sent', [
                // Kept left to right inside Arabic: "+966•••••••01", not "01•••••••966+".
                'contact' => "\u{2066}{$this->sentTo}\u{2069}",
                'channel' => __('identity::otp.sign_in.channels.'.$this->channel),
            ]);
        }

        return parent::getSubheading();
    }

    public function useCodeAction(): Action
    {
        return Action::make('useCode')
            ->label(__('identity::otp.sign_in.use_code'))
            ->link()
            ->visible(fn (): bool => $this->mode === 'password')
            ->action(function (): void {
                $this->mode = 'code';
                $this->resetErrorBag();
            });
    }

    public function usePasswordAction(): Action
    {
        return Action::make('usePassword')
            ->label(__('identity::otp.sign_in.use_password'))
            ->link()
            ->color('gray')
            ->visible(fn (): bool => $this->mode === 'code')
            ->action(function (): void {
                $this->mode = 'password';
                $this->reset('challengeId', 'sentTo', 'channel', 'channels');
                $this->resetErrorBag();
                $this->data['email'] = $this->contact ?? ($this->data['email'] ?? null);
            });
    }

    public function resendAction(): Action
    {
        return Action::make('resend')
            ->label(__('identity::otp.sign_in.resend'))
            ->link()
            ->visible(fn (): bool => $this->inCodeStep())
            ->action(fn () => $this->sendCode((string) $this->contact, $this->channel));
    }

    public function resendByAction(): Action
    {
        return Action::make('resendBy')
            ->label(fn (): string => __('identity::otp.sign_in.resend_by', ['channel' => __('identity::otp.sign_in.channels.'.$this->otherChannel())]))
            ->link()
            ->visible(fn (): bool => $this->inCodeStep() && $this->otherChannel() !== null)
            ->action(fn () => $this->sendCode((string) $this->contact, $this->otherChannel()));
    }

    public function changeContactAction(): Action
    {
        return Action::make('changeContact')
            ->label(__('identity::otp.sign_in.change'))
            ->link()
            ->color('gray')
            ->visible(fn (): bool => $this->inCodeStep())
            ->action(function (): void {
                $this->reset('challengeId', 'sentTo', 'channel', 'channels');
                $this->resetErrorBag();
                $this->data['email'] = $this->contact;
                $this->data['code'] = null;
            });
    }

    /**
     * Back from Google or Apple (SocialSignInController::finish): the same
     * door checks as a password, then the authenticator app's code for
     * whoever has it on (within ten minutes, as after a one-time code), else
     * straight in. A refusal on the way back is shown here.
     */
    private function finishSocialSignIn(): void
    {
        if (is_string($error = session()->pull('identity.social.error'))) {
            Notification::make()->title($error)->danger()->persistent()->send();
        }
        $passed = session()->pull('identity.social.passed');
        try {
            [$userId, $panel, $until] = is_string($passed) ? decrypt($passed) : [null, null, 0];
        } catch (Throwable) {
            return;
        }
        $user = $userId !== null && $panel === Filament::getCurrentOrDefaultPanel()->getId() && $until >= now()->getTimestamp()
            ? User::query()->find($userId) : null;
        if ($user === null) {
            return;
        }

        try {
            $allowed = $this->isUserAllowedToAccessPanel($user);
        } catch (ValidationException $held) {
            Notification::make()->title((string) collect($held->errors())->flatten()->first())->danger()->persistent()->send();

            return;
        }
        if (! $allowed) {
            Notification::make()->title(__('filament-panels::auth/pages/login.messages.failed'))->danger()->persistent()->send();

            return;
        }

        $challenge = $this->getMultiFactorChallenge();
        if ($provider = $challenge->getFirstEnabledProvider($user)) {
            $this->mode = 'code';
            $this->userUndertakingMultiFactorAuthentication = encrypt($user->getAuthIdentifier());
            $this->codePassed = encrypt([$user->getAuthIdentifier(), now()->addMinutes(10)->getTimestamp()]);
            $challenge->beforeChallenge($user, $provider);
            $this->multiFactorChallengeForm->fill();

            return;
        }

        Filament::auth()->login($user);
        session()->regenerate();
        $this->redirectIntended(Filament::getUrl());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [...AccountLookup::where((string) $data['email']), 'password' => $data['password']];
    }

    private function sendCode(string $contact, ?string $channel = null): void
    {
        try {
            $sent = app(RequestSignInCode::class)->handle($contact, $channel, request()->ip());
        } catch (ThrottleRequestsException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();

            return;
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['data.email' => $e->errors()['contact'] ?? $e->errors()['channel'] ?? [__('identity::otp.no_channel')]]);
        }

        $this->contact = trim($contact);
        $this->challengeId = $sent->challengeId;
        $this->sentTo = $sent->sentTo;
        $this->channel = $sent->channel;
        $this->channels = $sent->channels;
        $this->data['code'] = null;
        $this->resetErrorBag();
    }

    private function inCodeStep(): bool
    {
        return $this->mode === 'code' && $this->challengeId !== null;
    }

    /** Another way the code could go ("by text instead" when WhatsApp did not arrive). */
    private function otherChannel(): ?string
    {
        foreach ($this->channels as $channel) {
            if ($channel !== $this->channel) {
                return $channel;
            }
        }

        return null;
    }
}
