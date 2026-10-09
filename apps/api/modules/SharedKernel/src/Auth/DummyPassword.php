<?php

namespace Modules\SharedKernel\Auth;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** A password check that takes as long as a real one and never passes: for sign-ins without an account. */
final class DummyPassword
{
    /** A bcrypt hash (cost 12, the default) of random text nobody knows: as slow as a real account's, made once. */
    private const BCRYPT_12 = '$2y$12$bUVNPfaDxZt03akHz7bXtuyF47CydKPe16OIT9CiDhb9HRwjJnm5O';

    private static ?string $hash = null;

    /** @return false always, after as long as a real check takes */
    public static function check(#[\SensitiveParameter] string $password): bool
    {
        self::$hash ??= (int) config('hashing.bcrypt.rounds', 12) === 12 ? self::BCRYPT_12 : Hash::make(Str::random(40));
        Hash::check($password, self::$hash);

        return false;
    }
}
