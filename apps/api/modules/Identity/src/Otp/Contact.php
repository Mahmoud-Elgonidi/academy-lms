<?php

namespace Modules\Identity\Otp;

use Modules\SharedKernel\Contact\Phone;

/**
 * Where a code goes: an email (lower case) or a phone in E.164. Kept only as
 * a keyed hash, and shown back masked ("+966•••••••01", "h•••@example.test").
 */
final readonly class Contact
{
    private function __construct(public string $kind, public string $value) {}

    public static function email(string $email): self
    {
        return new self('email', mb_strtolower(trim($email)));
    }

    public static function phone(string $e164): self
    {
        return new self('phone', $e164);
    }

    /**
     * An email, or a phone written with its country ("+966…", "00966…").
     * Null for anything else: a local number names no one across academies.
     */
    public static function international(string $raw): ?self
    {
        $raw = trim($raw);
        if (str_contains($raw, '@')) {
            return filter_var($raw, FILTER_VALIDATE_EMAIL) ? self::email($raw) : null;
        }
        $digits = (string) preg_replace('/\D/', '', strtr($raw, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));
        if (! str_starts_with($raw, '+') && ! str_starts_with($digits, '00')) {
            return null;
        }
        $phone = Phone::normalize($raw);

        return $phone === null ? null : self::phone($phone);
    }

    public function isEmail(): bool
    {
        return $this->kind === 'email';
    }

    /** The channels a code can go by, in order. @return list<string> */
    public function channels(): array
    {
        return $this->isEmail() ? ['email'] : array_values((array) config('identity.otp.phone_channels'));
    }

    public function hash(): string
    {
        return hash_hmac('sha256', $this->kind.':'.$this->value, 'otp-contact|'.config('app.key'));
    }

    public function masked(): string
    {
        if ($this->isEmail()) {
            [$local, $domain] = explode('@', $this->value, 2) + [1 => ''];

            return mb_substr($local, 0, 1).'•••@'.$domain;
        }

        return substr($this->value, 0, 4).str_repeat('•', max(strlen($this->value) - 6, 1)).substr($this->value, -2);
    }
}
