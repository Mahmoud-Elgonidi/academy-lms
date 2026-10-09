<?php

namespace Modules\People\Filament\Directory;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\DTOs\UserDetails;
use Modules\People\Actions\Directory\SetPersonActive;
use Modules\People\Actions\SendWelcome;
use Modules\People\Queries\Models\Person;
use Modules\SharedKernel\Contact\Phone;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Timezones;

/** What the teachers', students' and guardians' screens share: who a person is and how to reach them, and turning an account off. */
final class DirectoryForm
{
    /** @return list<Component|Field> name, email, number, time zone and language, under a prefix ('' or 'guardian_') */
    public static function contact(string $prefix = '', bool $required = true): array
    {
        $l = 'people::directory.fields.';

        return [
            TextInput::make($prefix.'name')->label(__($l.($prefix === '' ? 'name' : 'guardian_name')))->required($required)->maxLength(255),
            TextInput::make($prefix.'email')->label(__($l.($prefix === '' ? 'email' : 'guardian_email')))->email()->maxLength(190)->extraInputAttributes(['dir' => 'ltr']),
            TextInput::make($prefix.'phone')->label(__($l.($prefix === '' ? 'phone' : 'guardian_phone')))->tel()->maxLength(30)
                ->helperText(__($l.'phone_hint'))->extraInputAttributes(['dir' => 'ltr']),
            Select::make($prefix.'timezone')->label(__($l.'timezone'))->searchable()->required($required)
                ->options(fn () => self::timezones())->default(fn () => app(TenantContext::class)->require()->defaultTimezone),
            ToggleButtons::make($prefix.'locale')->label(__($l.'locale'))->inline()->default('ar')
                ->options(__('people::directory.options.locales')),
        ];
    }

    /**
     * The form's name and ways in as Identity takes them: a number typed
     * without its country code takes the one of the person's time zone.
     *
     * @param  array<string, mixed>  $data
     */
    public static function details(array $data, string $prefix = ''): UserDetails
    {
        $timezone = $data[$prefix.'timezone'] ?? null;
        $raw = trim((string) ($data[$prefix.'phone'] ?? ''));
        $phone = $raw === '' ? null : Phone::normalize($raw, Phone::callingCodeFor($timezone ?? app(TenantContext::class)->require()->defaultTimezone));
        if ($raw !== '' && $phone === null) {
            throw ValidationException::withMessages(['phone' => __('people::directory.errors.phone', ['value' => $raw])]);
        }
        $email = trim((string) ($data[$prefix.'email'] ?? ''));

        return new UserDetails(trim((string) ($data[$prefix.'name'] ?? '')), $email === '' ? null : $email, $phone, $data[$prefix.'locale'] ?? null, $timezone);
    }

    /** @return array<string, mixed> a person's details as the form shows them */
    public static function fill(Person $person): array
    {
        return [
            'name' => $person->name,
            'email' => $person->email,
            'phone' => $person->phone_e164,
            'timezone' => $person->timezone ?? app(TenantContext::class)->require()->defaultTimezone,
            'locale' => $person->locale ?? 'ar',
        ];
    }

    /** @return array<string, string> */
    public static function timezones(): array
    {
        $zones = Timezones::all();

        return array_combine($zones, array_map(fn (string $z) => Timezones::city($z).' · '.$z, $zones));
    }

    /**
     * Runs a save; what it refuses is said in a notification and the form
     * stays as typed.
     *
     * @template T
     *
     * @param  Closure(): T  $save
     * @return T
     */
    public static function attempt(Closure $save): mixed
    {
        try {
            return $save();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(implode(' ', $e->validator->errors()->all()))->send();

            throw new Halt;
        }
    }

    /**
     * The edit pages' header: turn the account off or on, and send the
     * welcome link again to whoever has not chosen a password yet.
     *
     * @param  Closure(): Person  $person
     * @return list<Action>
     */
    public static function accountActions(Closure $person, Closure $refresh): array
    {
        $a = 'people::directory.actions.';

        return [
            Action::make('resendWelcome')->label(__($a.'resend_welcome'))->icon('heroicon-o-paper-airplane')->color('gray')
                ->visible(fn () => ($p = $person())->isActive() && ! $p->hasPassword() && ! $p->is_managed_minor && ($p->email !== null || $p->phone_e164 !== null))
                ->action(function () use ($person, $a) {
                    app(SendWelcome::class)->handle([$person()->id]);
                    Notification::make()->success()->title(__($a.'welcome_sent'))->send();
                }),
            Action::make('disable')->label(__($a.'disable'))->icon('heroicon-o-lock-closed')->color('danger')
                ->visible(fn () => $person()->isActive())
                ->requiresConfirmation()->modalDescription(__($a.'disable_body'))
                ->action(function () use ($person, $refresh, $a) {
                    self::attempt(fn () => app(SetPersonActive::class)->handle($person()->id, false));
                    $refresh();
                    Notification::make()->success()->title(__($a.'disabled'))->send();
                }),
            Action::make('enable')->label(__($a.'enable'))->icon('heroicon-o-lock-open')->color('success')
                ->visible(fn () => ! $person()->isActive())
                ->action(function () use ($person, $refresh, $a) {
                    self::attempt(fn () => app(SetPersonActive::class)->handle($person()->id, true));
                    $refresh();
                    Notification::make()->success()->title(__($a.'enabled'))->send();
                }),
        ];
    }

    /** The account column: on, off, or waiting for its first password. */
    public static function status(Person $person): string
    {
        return match (true) {
            ! $person->isActive() => 'disabled',
            ! $person->hasPassword() && ! $person->is_managed_minor => 'invited',
            default => 'active',
        };
    }
}
