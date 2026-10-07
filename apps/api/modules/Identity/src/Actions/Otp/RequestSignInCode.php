<?php

namespace Modules\Identity\Actions\Otp;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Accounts\AccountLookup;
use Modules\Identity\Contracts\DTOs\OtpMessage;
use Modules\Identity\Enums\OtpPurpose;
use Modules\Identity\Models\User;
use Modules\Identity\Otp\Challenges;
use Modules\Identity\Otp\Contact;
use Modules\Identity\Otp\SentCode;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * "Send me a code" in an academy: the email or mobile number as people write
 * it. The answer is the same for a contact with no account here; only an
 * active account gets a message, in its own language and the academy's name.
 */
final class RequestSignInCode
{
    public function __construct(
        private readonly Challenges $challenges,
        private readonly TenantContext $tenants,
        private readonly BrandingApi $branding,
        private readonly TenantDirectory $directory,
    ) {}

    public function handle(string $login, ?string $channel = null, ?string $ip = null): SentCode
    {
        $where = AccountLookup::where($login);
        $contact = match (true) {
            isset($where['email']) => Contact::email($where['email']),
            isset($where['phone_e164']) => Contact::phone($where['phone_e164']),
            default => throw ValidationException::withMessages(['contact' => __('identity::otp.contact_needed')]),
        };
        $channel = $this->challenges->channelFor($contact, $channel);

        $user = User::query()->where(array_key_first($where), $contact->value)->first();
        $user = $user?->isActive() ? $user : null;

        $tenant = $this->tenants->require();
        $compose = $user === null ? null : function (string $code) use ($user, $contact, $channel, $tenant): OtpMessage {
            $locale = $user->preferredLocale() ?? app()->getLocale();
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
        };

        return $this->challenges->open(OtpPurpose::SignIn, $tenant->id, $contact, $channel, $user?->id, $ip, $compose);
    }
}
