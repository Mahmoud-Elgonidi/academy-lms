<?php

namespace Modules\Crm\Support;

use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\ContactMessage;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Crm\Models\Lead;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Messages to a lead's family about its trial (docs/architecture/02, section
 * 27): to their accounts (the parent, or the adult learner; never a child's
 * own account) by the template's channels, WhatsApp among them; a family
 * with no account (none was made, or it was erased with its number left on
 * the lead) hears by WhatsApp and email at the number and address they gave
 * (NotificationsApi::sendToContact, nothing kept). Their language is the
 * account's, else the lead's.
 */
final class FamilyContact
{
    public function __construct(private readonly NotificationsApi $notifications, private readonly TenantContext $tenants) {}

    /** @return list<string> the family's accounts that hear about the trial */
    public static function recipients(Lead $lead): array
    {
        return array_values(array_unique(array_filter([$lead->contact_id, $lead->for_child ? null : $lead->learner_id])));
    }

    public function locale(Lead $lead): string
    {
        $locale = $lead->contact_id !== null ? DB::table('users')->where('id', $lead->contact_id)->value('locale') : null;

        return ($locale ?? $lead->locale) === 'en' ? 'en' : 'ar';
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $action
     * @return int how many it went to (people, or channels for a family with no account)
     */
    public function send(Lead $lead, string $template, array $data, array $action, string $dedupeKey): int
    {
        $to = self::recipients($lead);
        if ($to !== []) {
            return $this->notifications->send(new Message($template, $to, $data, $action, $dedupeKey));
        }
        if ($lead->phone_e164 === null && $lead->email === null) {
            return 0;
        }

        return $this->notifications->sendToContact(new ContactMessage(
            template: $template,
            name: $lead->contact_name,
            phone: $lead->phone_e164,
            email: $lead->email,
            locale: $lead->locale === 'en' ? 'en' : 'ar',
            timezone: $lead->timezone ?: $this->tenants->require()->defaultTimezone,
            data: $data,
        ));
    }
}
