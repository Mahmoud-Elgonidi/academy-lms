<?php

namespace Modules\Identity\Actions\Otp;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\DTOs\OtpMessage;
use Modules\Identity\Contracts\DTOs\PhoneCodeSent;
use Modules\Identity\Enums\OtpPurpose;
use Modules\Identity\Otp\Challenges;
use Modules\Identity\Otp\Contact;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Contact\Phone;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * A code to a mobile number that proves it is the visitor's before a free
 * trial is booked for it from the academy's page (purpose "trial"). It goes
 * whether or not the number has an account, so the answer says nothing about
 * who is here; the usual limits hold (one a minute, caps per number and
 * address), and the code leaves after the response.
 */
final class SendPhoneCode
{
    public function __construct(
        private readonly Challenges $challenges,
        private readonly TenantContext $tenants,
        private readonly BrandingApi $branding,
        private readonly TenantDirectory $directory,
    ) {}

    public function handle(string $phone, string $locale, ?string $channel = null, ?string $ip = null): PhoneCodeSent
    {
        $e164 = Phone::normalize($phone);
        if ($e164 === null) {
            throw ValidationException::withMessages(['phone' => __('identity::otp.contact_needed')]);
        }
        $contact = Contact::phone($e164);
        $channel = $this->challenges->channelFor($contact, $channel);
        $tenant = $this->tenants->require();
        $locale = $locale === 'en' ? 'en' : 'ar';

        $sent = $this->challenges->open(OtpPurpose::Trial, $tenant->id, $contact, $channel, null, $ip, function (string $code) use ($contact, $channel, $tenant, $locale): OtpMessage {
            $branding = $this->branding->current();

            return new OtpMessage(
                channel: $channel,
                destination: $contact->value,
                code: $code,
                minutes: (int) config('identity.otp.minutes'),
                locale: $locale,
                senderName: $branding->name($locale),
                logoUrl: $branding->logoUrl,
                colour: $branding->tokens['primary'] ?? null,
                host: parse_url($this->directory->baseUrl($tenant), PHP_URL_HOST) ?: null,
            );
        });

        return new PhoneCodeSent($sent->challengeId, $sent->channel, $sent->sentTo, $sent->expiresAt, $sent->resendAfter, $sent->channels);
    }

    /** Throws a ValidationException on "code" unless it is the right code, sent to this very number, and still good. */
    public function verify(string $challengeId, string $code, string $phone): void
    {
        $e164 = Phone::normalize($phone);
        if ($e164 === null) {
            throw ValidationException::withMessages(['code' => __('identity::otp.expired')]);
        }
        $this->challenges->verify($challengeId, $code, OtpPurpose::Trial, $this->tenants->require()->id, Contact::phone($e164));
    }
}
