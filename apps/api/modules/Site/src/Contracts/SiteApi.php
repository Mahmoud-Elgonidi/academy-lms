<?php

namespace Modules\Site\Contracts;

use Modules\Site\Contracts\DTOs\JoinRequestData;

/** What other modules may ask of the academy's page (the CRM, which makes leads of its trial requests). */
interface SiteApi
{
    /** A request from the page in the current academy; null when there is none (swept, or another academy's). */
    public function joinRequest(string $id): ?JoinRequestData;

    /** The lead the CRM made of a request (or the open lead it went to), so the requests screen opens it. */
    public function linkLead(string $requestId, string $leadId): void;
}
