<?php

namespace Modules\Identity\Filament\Pages;

use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Contact\Phone;
use SensitiveParameter;

/**
 * Sign-in for every panel with what people have: an email, a mobile number
 * (as they write it: "+20 101…", "00966…", or local "0101…" when only one
 * account here ends with it), or a username.
 */
final class SignIn extends Login
{
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('identity::auth.sign_in.login'))
            ->required()
            ->maxLength(255)
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [...self::lookup(trim((string) $data['email'])), 'password' => $data['password']];
    }

    /** @return array<string, string> the column and value to find the account by */
    public static function lookup(string $login): array
    {
        if (str_contains($login, '@')) {
            return ['email' => $login];
        }
        if (preg_match('/^[\s+\d٠-٩۰-۹()\-]{7,}$/u', $login)) {
            $phone = Phone::normalize($login);
            $digits = ltrim((string) preg_replace('/\D/', '', strtr($login, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'])), '0');
            if (! str_starts_with(trim($login), '+') && ! str_starts_with(trim($login), '00') && strlen($digits) >= 8) {
                // A local number: the one account here whose number ends with it.
                $matches = DB::table('users')->where('phone_e164', 'like', '%'.$digits)->limit(2)->pluck('phone_e164');
                if ($matches->count() === 1) {
                    return ['phone_e164' => (string) $matches->first()];
                }
            }

            return ['phone_e164' => $phone ?? $login];
        }

        return ['username' => $login];
    }
}
