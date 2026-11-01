<?php

namespace Modules\Crm\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadTrial;
use Modules\Identity\Contracts\IdentityApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\Site\Contracts\SiteApi;

/**
 * Every hour (crm:follow-up): a new lead nobody has talked to once the
 * academy's promised hours (site_settings.response_hours) have passed, and
 * a trial that took place a day ago with no assessment yet, each told once
 * to whoever follows the lead (its owner, else everyone who works leads),
 * who can ask the teacher. A lead older than a week is left to the board.
 */
final class FollowUpLeads
{
    public const LOOK_BACK_DAYS = 7;

    public const ASSESS_WITHIN_HOURS = 24;

    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly IdentityApi $identity,
        private readonly SiteApi $site,
    ) {}

    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $hours = $this->site->trialSettings()->responseHours;
        $sent = 0;
        $managers = null;
        $sales = function (?string $ownerId) use (&$managers): array {
            return $ownerId !== null ? [$ownerId] : ($managers ??= $this->identity->usersWithAbility(Ability::ManageLeads));
        };

        $waiting = Lead::query()->where('stage', LeadStage::New->value)->whereNull('last_contact_at')
            ->where('created_at', '<=', $now->subHours($hours))->where('created_at', '>', $now->subDays(self::LOOK_BACK_DAYS))
            ->orderBy('created_at')->get();
        foreach ($waiting as $lead) {
            $sent += $this->notifications->send(new Message('crm.lead_waiting', $sales($lead->owner_id), [
                'name' => $lead->contact_name,
                'hours' => (string) $hours,
            ], ['screen' => 'lead', 'id' => $lead->id], "lead-waiting:{$lead->id}"));
        }

        $unassessed = LeadTrial::query()->with('lead')->where('status', 'attended')->whereNull('assessed_at')
            ->where('starts_at', '<=', $now->subHours(self::ASSESS_WITHIN_HOURS))->where('starts_at', '>', $now->subDays(self::LOOK_BACK_DAYS))
            ->orderBy('starts_at')->get();
        foreach ($unassessed as $trial) {
            $teacher = (string) DB::table('users')->where('id', $trial->teacher_id)->value('name');
            $sent += $this->notifications->send(new Message('crm.trial_unassessed', $sales($trial->lead->owner_id), [
                'name' => $trial->lead->learnerName(),
                'teacher' => $teacher,
            ], ['screen' => 'trial', 'id' => $trial->id], "trial-unassessed:{$trial->id}"));
        }

        return $sent;
    }
}
