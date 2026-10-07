<?php

namespace Modules\Identity\Actions\Otp;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\DTOs\OtpMessage;
use Modules\Identity\Enums\OtpPurpose;
use Modules\Identity\Otp\Challenges;
use Modules\Identity\Otp\Contact;
use Modules\Identity\Otp\SentCode;
use Modules\Platform\Contracts\AccountDirectory;

/**
 * The shared app's first screen: an email or an international mobile number,
 * to find the person's accounts in every academy. Same answer whether or not
 * it has any; a message goes out only when it does, in the platform's name.
 */
final class RequestDiscoveryCode
{
    public function __construct(private readonly Challenges $challenges, private readonly AccountDirectory $accounts) {}

    public function handle(string $contact, ?string $channel = null, ?string $ip = null): SentCode
    {
        $parsed = Contact::international($contact) ?? throw ValidationException::withMessages(['contact' => __('identity::otp.international')]);
        $channel = $this->challenges->channelFor($parsed, $channel);

        $found = $parsed->isEmail() ? $this->accounts->withEmail($parsed->value) : $this->accounts->withPhone($parsed->value);
        $locale = app()->getLocale();
        $compose = $found === [] ? null : fn (string $code): OtpMessage => new OtpMessage(
            channel: $channel,
            destination: $parsed->value,
            code: $code,
            minutes: (int) config('identity.otp.minutes'),
            locale: $locale,
            senderName: (string) config('app.name'),
        );

        return $this->challenges->open(OtpPurpose::Discover, null, $parsed, $channel, null, $ip, $compose);
    }
}
