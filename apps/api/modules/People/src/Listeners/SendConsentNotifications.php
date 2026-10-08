<?php

namespace Modules\People\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Identity\Contracts\IdentityApi;
use Modules\People\Consent\ConsentRules;
use Modules\People\Contracts\Events\ConsentRequested;
use Modules\People\Contracts\Events\ConsentWithdrawn;
use Modules\SharedKernel\Authorization\Ability;

/**
 * People's messages (wording in lang/{ar,en}/notifications.php): the request
 * to a guardian, with their own link to read and agree without signing in,
 * and a withdrawal for whoever follows the families.
 */
final class SendConsentNotifications
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly IdentityApi $identity,
        private readonly ConsentRules $rules,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $a = 'people::notifications.audience.';
        foreach ([
            ['consent_request', 'lessons', $a.'guardians', ['whatsapp', 'email', 'push'], ['student' => 'يوسف', 'link' => 'https://demo.lms.test/consent/…']],
            ['consent_withdrawn', 'staff', $a.'office', ['push', 'email'], ['student' => 'يوسف', 'guardian' => 'خالد']],
        ] as [$name, $category, $audience, $channels, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "people.{$name}", category: $category,
                title: "people::notifications.{$name}.title", body: "people::notifications.{$name}.body",
                audience: $audience, channels: $channels, sample: $sample,
            ));
        }
    }

    public function requested(ConsentRequested $event): void
    {
        // Asked when the guardian was linked; by now the birth date may say the student needs no consent, or one came in.
        if (! $this->rules->state($event->studentId)->waiting()) {
            return;
        }
        $student = (string) DB::table('users')->where('id', $event->studentId)->value('name');
        foreach ($event->guardianIds as $guardianId) {
            $this->notifications->send(new Message('people.consent_request', [$guardianId], ['student' => $student],
                ['screen' => 'guardian_consent', 'id' => $event->studentId], "consent-request:{$event->studentId}:{$guardianId}:".($event->again ? Str::ulid() : now()->format('Y-m-d'))));
        }
    }

    public function withdrawn(ConsentWithdrawn $event): void
    {
        $names = DB::table('users')->whereIn('id', [$event->studentId, $event->byUserId])->pluck('name', 'id');
        $this->notifications->send(new Message('people.consent_withdrawn', $this->identity->usersWithAbility(Ability::ManageConsents), [
            'student' => (string) ($names[$event->studentId] ?? ''),
            'guardian' => (string) ($names[$event->byUserId] ?? ''),
        ], ['screen' => 'guardian_consents', 'id' => $event->studentId], "consent-withdrawn:{$event->consentId}"));
    }
}
