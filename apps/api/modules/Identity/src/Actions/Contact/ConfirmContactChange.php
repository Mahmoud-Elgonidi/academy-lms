<?php

namespace Modules\Identity\Actions\Contact;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Contracts\Events\ContactChanged;
use Modules\Identity\Enums\OtpPurpose;
use Modules\Identity\Models\User;
use Modules\Identity\Otp\Challenges;
use Modules\Identity\Otp\Contact;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The code from the new address came back: the address becomes the person's
 * email or mobile number (a way in from now on). The audit says which one
 * changed, never the address, and the person hears of it on every device and
 * channel they have (ContactChanged), in case it was not them.
 */
final class ConfirmContactChange
{
    public function __construct(
        private readonly Challenges $challenges,
        private readonly TenantContext $tenants,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(User $user, string $challengeId, string $code): User
    {
        $challenge = $this->challenges->verify($challengeId, $code, OtpPurpose::ChangeContact, $this->tenants->require()->id);
        if ($challenge->user_id !== $user->id) {
            throw ValidationException::withMessages(['code' => __('identity::otp.expired')]);
        }
        $kind = $challenge->channel === 'email' ? 'email' : 'phone';
        $value = (string) $challenge->destination;

        return DB::transaction(function () use ($user, $kind, $value) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            // Checked again: another account may have taken it while the code was on its way.
            $contact = RequestContactChange::contact($user, $kind, $value);
            $user->forceFill([$kind === 'email' ? 'email' : 'phone_e164' => $contact->value])->save();

            $this->audit->record('identity.contact_changed', 'user', $user->id, [
                'name' => $user->name, 'email' => $kind === 'email', 'phone' => $kind === 'phone', 'self' => true,
            ]);
            DB::afterCommit(fn () => event(new ContactChanged($user->id, $kind, true)));

            return $user;
        });
    }

    /** The masked address a confirmed change would set, to show while the code is awaited. */
    public static function masked(string $kind, string $value): string
    {
        return ($kind === 'email' ? Contact::email($value) : Contact::phone($value))->masked();
    }
}
