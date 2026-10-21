<?php

namespace Modules\Crm\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Actions\CreateLead;
use Modules\Crm\Enums\LeadSource;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;
use Modules\Crm\Support\Countries;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Site\Contracts\Events\JoinRequested;
use Modules\Site\Contracts\SiteApi;
use Throwable;

/**
 * A free trial asked for on the academy's page becomes a lead (source
 * "website", with its campaign, subject, learner and clock), whether or not
 * the academy's plan has the CRM's screens: the data keeps flowing, and the
 * screens open when the plan does. A number an open lead already has is that
 * lead asking again: its timeline says so, and the request points at it.
 * Nothing here ever reaches the visitor (the page answered already): a lead
 * that cannot be made is written in the log, never in their answer, and
 * never with the other lead's name.
 */
final class FollowSiteRequests
{
    public function __construct(private readonly SiteApi $site, private readonly CreateLead $create, private readonly TenantContext $tenants) {}

    public function joinRequested(JoinRequested $event): void
    {
        if ($event->kind !== 'trial') {
            return;
        }
        try {
            $this->follow($event->requestId);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function follow(string $requestId): void
    {
        $request = $this->site->joinRequest($requestId);
        if ($request === null || $request->leadId !== null) {
            return;
        }
        $country = $request->country !== null && isset(Countries::ZONES[$request->country]) ? $request->country : null;

        try {
            $lead = $this->create->handle([
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
                'source' => LeadSource::Website->value,
                'utm_source' => $request->utmSource,
                'utm_medium' => $request->utmMedium,
                'utm_campaign' => $request->utmCampaign,
                'notes' => $request->message,
            ], null);
        } catch (ValidationException $e) {
            // The same number on a lead still followed (CreateLead refuses it): that lead asked again.
            $open = Lead::query()->where('phone_e164', $request->phone)
                ->whereIn('stage', array_map(fn (LeadStage $s) => $s->value, LeadStage::OPEN))->orderBy('created_at')->first();
            if ($open === null) {
                Log::warning('crm: a trial request from the academy\'s page did not become a lead', ['request' => $requestId, 'fields' => array_keys($e->errors())]);

                return;
            }
            LeadActivity::record($open, 'website_again', $request->message, ['request' => $request->id], null);
            $this->site->linkLead($request->id, $open->id);

            return;
        }
        $this->site->linkLead($request->id, $lead->id);
    }
}
