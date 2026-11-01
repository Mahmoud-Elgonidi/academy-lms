<?php

namespace Modules\Crm\Listeners;

use Illuminate\Validation\ValidationException;
use Modules\Crm\Contracts\Events\LeadStageChanged;
use Modules\Crm\Support\WebsiteLeads;
use Modules\Site\Contracts\Events\JoinRequested;
use Modules\Site\Contracts\SiteApi;
use Throwable;

/**
 * A free trial asked for on the academy's page becomes a lead
 * (Support\WebsiteLeads), whether or not the academy's plan has the CRM's
 * screens: the data keeps flowing, and the screens open when the plan does.
 * A number an open lead already has is that lead asking again. Nothing here
 * ever reaches the visitor (the page answered already): a lead that cannot
 * be made is flagged on the request (the requests screen says so, and
 * someone adds it by hand), never put in their answer. The request's status
 * then follows the lead's stage (one status).
 */
final class FollowSiteRequests
{
    public function __construct(private readonly SiteApi $site, private readonly WebsiteLeads $leads) {}

    public function joinRequested(JoinRequested $event): void
    {
        if ($event->kind !== 'trial') {
            return;
        }
        $request = $this->site->joinRequest($event->requestId);
        if ($request === null || $request->leadId !== null) {
            return;
        }
        try {
            $this->leads->leadFor($request);
        } catch (ValidationException) {
            $this->site->leadFailed($request->id, 'invalid');
        } catch (Throwable $e) {
            report($e);
            $this->site->leadFailed($request->id, 'error');
        }
    }

    public function stageChanged(LeadStageChanged $event): void
    {
        $this->site->leadMoved($event->leadId, $event->to);
    }
}
