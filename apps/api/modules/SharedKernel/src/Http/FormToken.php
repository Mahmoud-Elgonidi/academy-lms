<?php

namespace Modules\SharedKernel\Http;

use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * A public form's token: handed out with the page (encrypted with APP_KEY,
 * naming the form and when it was given) and checked when the form comes
 * back. A form sent sooner than a person could fill it, or long after, or for
 * another form, is a machine's: the door answers as if it took it, and keeps
 * nothing.
 */
final class FormToken
{
    /** Sooner than this after the page was given, no one typed it. */
    public const MIN_SECONDS = 3;

    /** A page left open longer is asked to be opened again. */
    public const MAX_SECONDS = 6 * 3600;

    public static function issue(string $form): string
    {
        return Crypt::encryptString(json_encode(['f' => $form, 't' => now()->getTimestamp()], JSON_THROW_ON_ERROR));
    }

    public static function passes(string $form, ?string $token): bool
    {
        if ($token === null || $token === '' || strlen($token) > 1000) {
            return false;
        }
        try {
            $data = json_decode(Crypt::decryptString($token), true, 4, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }
        $age = now()->getTimestamp() - (int) ($data['t'] ?? 0);

        return ($data['f'] ?? null) === $form && $age >= self::MIN_SECONDS && $age <= self::MAX_SECONDS;
    }
}
