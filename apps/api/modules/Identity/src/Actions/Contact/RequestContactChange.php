<?php

namespace Modules\Identity\Actions\Contact;

use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\DTOs\OtpMessage;
use Modules\Identity\Enums\OtpPurpose;
use Modules\Identity\Models\User;
use Modules\Identity\Otp\Challenges;
use Modules\Identity\Otp\Contact;
use Modules\Identity\Otp\SentCode;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Contact\Phone;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The signed-in person asks to make a new email or mobile number theirs: a
 * code goes to the new address, and nothing changes until it comes back
 * (ConfirmContactChange). The new address must not be another account's in
 * this academy. Never while the platform team is signed in as them, and not
 * for a child the academy runs under a guardian (the guardian or the academy
 * changes those).
 */
final class RequestContactChange
{
    public const KINDS = ['email', 'phone'];

    public function __construct(
        private readonly Challenges $challenges,
        private readonly TenantContext $tenants,
        private readonly BrandingApi $branding,
        private readonly TenantDirectory $directory,
    ) {}

    public function handle(User $user, string $kind, string $value, ?string $channel = null, ?string $ip = null): SentCode
    {
        $contact = self::contact($user, $kind, $value);
        $channel = $this->challenges->channelFor($contact, $channel);
        $tenant = $this->tenants->require();

        $compose = function (string $code) use ($user, $contact, $channel, $tenant): OtpMessage {
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

        return $this->challenges->open(OtpPurpose::ChangeContact, $tenant->id, $contact, $channel, $user->id, $ip, $compose);
    }

    /** The new address, checked: well formed, not the current one, nobody else's here. */
    public static function contact(User $user, string $kind, string $value): Contact
    {
        $e = 'identity::settings.contact.errors.';
        if (AuditActor::current()->onBehalfOf !== null) {
            throw ValidationException::withMessages(['value' => __('identity::impersonation.errors.contact')]);
        }
        if ($user->is_managed_minor) {
            throw ValidationException::withMessages(['value' => __($e.'managed')]);
        }
        if (! in_array($kind, self::KINDS, true)) {
            throw ValidationException::withMessages(['kind' => __($e.'kind')]);
        }
        $value = trim($value);
        if ($kind === 'email') {
            if (filter_var($value, FILTER_VALIDATE_EMAIL) === false || mb_strlen($value) > 255) {
                throw ValidationException::withMessages(['value' => __($e.'email')]);
            }
            $contact = Contact::email($value);
        } else {
            $phone = Phone::normalize($value, Phone::callingCodeFor($user->timezone ?? app(TenantContext::class)->require()->defaultTimezone));
            if ($phone === null) {
                throw ValidationException::withMessages(['value' => __($e.'phone')]);
            }
            $contact = Contact::phone($phone);
        }

        $column = $kind === 'email' ? 'email' : 'phone_e164';
        if ((string) $user->getAttribute($column) === $contact->value) {
            throw ValidationException::withMessages(['value' => __($e.'same')]);
        }
        if (User::query()->where($column, $contact->value)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['value' => __($e.'taken')]);
        }

        return $contact;
    }
}
