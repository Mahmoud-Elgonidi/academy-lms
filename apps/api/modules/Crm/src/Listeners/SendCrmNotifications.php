<?php

namespace Modules\Crm\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Crm\Contracts\Events\LeadAssigned;
use Modules\Crm\Contracts\Events\TrialAssessed;
use Modules\Crm\Contracts\Events\TrialCompleted;
use Modules\Crm\Contracts\Events\TrialMissed;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadTrial;
use Modules\Identity\Contracts\IdentityApi;
use Modules\SharedKernel\Authorization\Ability;

/**
 * The CRM's messages (wording in lang/{ar,en}/notifications.php): a lead
 * handed to someone, a trial for its teacher to assess, and for sales a
 * trial assessed or not held. Sales means the lead's owner, or everyone who
 * works leads when nobody owns it. The family's own messages about the
 * trial lesson are Scheduling's.
 */
final class SendCrmNotifications
{
    public function __construct(private readonly NotificationsApi $notifications, private readonly IdentityApi $identity) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $a = 'crm::notifications.audience.';
        foreach ([
            ['lead_assigned', $a.'sales', ['push', 'email'], ['name' => 'أم يوسف', 'source' => 'واتساب']],
            ['assess_trial', $a.'teacher', ['push', 'whatsapp'], ['student' => 'يوسف', 'subject' => 'القرآن', 'when' => 'الثلاثاء 13 أكتوبر · 6:00 م']],
            ['trial_missed', $a.'sales', ['push'], ['name' => 'أم يوسف', 'outcome' => 'لم يحضر الطالب']],
            ['trial_assessed', $a.'sales', ['push', 'email'], ['name' => 'أم يوسف', 'teacher' => 'أ. نور', 'level' => 'مبتدئ: الحروف والحركات']],
        ] as [$name, $audience, $channels, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "crm.{$name}", category: 'staff',
                title: "crm::notifications.{$name}.title", body: "crm::notifications.{$name}.body",
                audience: $audience, channels: $channels, sample: $sample,
            ));
        }
    }

    public function assigned(LeadAssigned $event): void
    {
        $lead = Lead::query()->find($event->leadId);
        if ($lead === null || $event->ownerId === $event->actorId) {
            return;
        }
        $this->notifications->send(new Message('crm.lead_assigned', [$event->ownerId], [
            'name' => $lead->contact_name,
            'source' => new Translatable('crm::leads.sources.'.$lead->source->value),
        ], ['screen' => 'lead', 'id' => $lead->id], "lead-assigned:{$lead->id}:{$event->ownerId}"));
    }

    public function trialCompleted(TrialCompleted $event): void
    {
        $trial = LeadTrial::query()->with('lead')->find($event->trialId);
        if ($trial === null) {
            return;
        }
        $this->notifications->send(new Message('crm.assess_trial', [$trial->teacher_id], [
            'student' => $trial->lead->learnerName(),
            'subject' => $this->subject($trial->lead->subject_id),
            'when' => $trial->starts_at,
        ], ['screen' => 'trial', 'id' => $trial->id], "assess-trial:{$trial->id}"));
    }

    public function trialMissed(TrialMissed $event): void
    {
        $lead = Lead::query()->find($event->leadId);
        if ($lead === null) {
            return;
        }
        $this->notifications->send(new Message('crm.trial_missed', $this->sales($lead), [
            'name' => $lead->learnerName(),
            'outcome' => new Translatable('crm::trials.outcomes.'.$event->outcome),
        ], ['screen' => 'lead', 'id' => $lead->id], "trial-missed:{$event->trialId}"));
    }

    public function trialAssessed(TrialAssessed $event): void
    {
        $trial = LeadTrial::query()->with('lead')->find($event->trialId);
        if ($trial === null) {
            return;
        }
        $this->notifications->send(new Message('crm.trial_assessed', array_values(array_diff($this->sales($trial->lead), [$event->actorId])), [
            'name' => $trial->lead->learnerName(),
            'teacher' => (string) DB::table('users')->where('id', $trial->teacher_id)->value('name'),
            'level' => (string) $trial->level,
        ], ['screen' => 'lead', 'id' => $trial->lead_id], "trial-assessed:{$trial->id}"));
    }

    /** @return list<string> */
    private function sales(Lead $lead): array
    {
        return $lead->owner_id !== null ? [$lead->owner_id] : $this->identity->usersWithAbility(Ability::ManageLeads);
    }

    private function subject(?string $subjectId): Localized|string
    {
        $s = $subjectId ? DB::table('subjects')->where('id', $subjectId)->first(['name_ar', 'name_en']) : null;

        return $s ? new Localized($s->name_ar, $s->name_en) : '';
    }
}
