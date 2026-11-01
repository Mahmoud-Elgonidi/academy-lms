<?php

namespace Modules\Crm\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Actions\AssignLead;
use Modules\Crm\Actions\CreateLead;
use Modules\Crm\Enums\LeadSource;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Site\Contracts\DTOs\JoinRequestData;
use Modules\Site\Contracts\SiteApi;

/**
 * A free trial asked for on the academy's page, as a lead (source
 * "website", with its campaign, subject, learner, clock, the times that suit
 * and the teacher they would rather have): the lead it already went to, the
 * open lead with its number for the same learner (that lead asking again,
 * said on its timeline), or a new one (a parent asking for a second child
 * gets a lead for that child), handed to whoever works leads next
 * (AssignLead) while the academy shares them out. The request points at it.
 */
final class WebsiteLeads
{
    public function __construct(
        private readonly SiteApi $site,
        private readonly CreateLead $create,
        private readonly AssignLead $assign,
        private readonly TenantContext $tenants,
    ) {}

    /** @throws ValidationException when no lead can be made of it (its details) */
    public function leadFor(JoinRequestData $request): Lead
    {
        $linked = $request->leadId !== null ? Lead::query()->find($request->leadId) : null;
        if ($linked !== null && $linked->stage->isOpen()) {
            return $linked;
        }
        $open = self::openLead($request->phone, $request->forChild, $request->childName);
        if ($open !== null) {
            LeadActivity::record($open, 'website_again', $request->message, ['request' => $request->id], null);
            $this->site->linkLead($request->id, $open->id);

            return $open;
        }

        return DB::transaction(function () use ($request) {
            $lead = $this->create->handle($this->fields($request), null, sibling: true);
            // Taken in turn under the academy's lock, so two leads at once go to two people.
            $owner = $this->assign->nextForWebsite();
            if ($owner !== null) {
                $this->assign->handle($lead, $owner, null);
            }
            $this->site->linkLead($request->id, $lead->id);

            return $lead;
        });
    }

    /** The open lead with this number that takes this ask (Lead::takesAsk: the same learner, or one not known yet). */
    public static function openLead(?string $phone, bool $forChild, ?string $childName): ?Lead
    {
        return $phone === null ? null : Lead::query()->where('phone_e164', $phone)
            ->whereIn('stage', array_map(fn (LeadStage $s) => $s->value, LeadStage::OPEN))->orderBy('created_at')->get()
            ->first(fn (Lead $l) => $l->takesAsk($forChild, $childName));
    }

    /** @return array<string, mixed> */
    public function fields(JoinRequestData $request): array
    {
        $country = $request->country !== null && isset(Countries::ZONES[$request->country]) ? $request->country : null;

        return [
            'contact_name' => $request->name,
            'phone_e164' => $request->phone,
            'email' => $request->email,
            'country' => $country,
            'timezone' => $request->timezone ?? ($country !== null ? Countries::ZONES[$country] : $this->tenants->require()->defaultTimezone),
            'locale' => $request->locale,
            'for_child' => $request->forChild,
            'learner_name' => $request->forChild ? $request->childName : null,
            'learner_birth_year' => $request->forChild ? $request->childBirthYear : null,
            'subject_id' => $request->subjectId,
            'preferred_days' => $request->preferredDays,
            'preferred_from' => $request->preferredFrom,
            'preferred_to' => $request->preferredFrom !== null ? $request->preferredTo : null,
            'teacher_gender' => $request->teacherGender,
            'source' => LeadSource::Website->value,
            'utm_source' => $request->utmSource,
            'utm_medium' => $request->utmMedium,
            'utm_campaign' => $request->utmCampaign,
            'notes' => $request->message,
        ];
    }
}
