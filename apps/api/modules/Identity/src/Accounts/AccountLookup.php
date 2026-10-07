<?php

namespace Modules\Identity\Accounts;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Contact\Phone;

/**
 * Finds the account in the current academy that a sign-in field names, as
 * people write it: an email, a mobile number ("+20 101…", "00966…", or local
 * "0101…" when only one account here ends with it), or a username.
 */
final class AccountLookup
{
    /** @return array{email: string}|array{phone_e164: string}|array{username: string} the column and value to find the account by */
    public static function where(string $login): array
    {
        $login = trim($login);
        if (str_contains($login, '@')) {
            return ['email' => $login];
        }
        if (preg_match('/^[\s+\d٠-٩۰-۹()\-]{7,}$/u', $login)) {
            $phone = Phone::normalize($login);
            $digits = ltrim((string) preg_replace('/\D/', '', strtr($login, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'])), '0');
            if (! str_starts_with($login, '+') && ! str_starts_with($login, '00') && strlen($digits) >= 8) {
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
