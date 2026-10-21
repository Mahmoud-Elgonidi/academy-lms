<?php

namespace Modules\Site;

use Illuminate\Support\Str;
use Modules\Site\Contracts\DTOs\JoinRequestData;
use Modules\Site\Contracts\SiteApi;
use Modules\Site\Models\JoinRequest;

final class SiteService implements SiteApi
{
    public function joinRequest(string $id): ?JoinRequestData
    {
        $r = Str::isUuid($id) ? JoinRequest::query()->find($id) : null;

        return $r === null ? null : new JoinRequestData(
            id: $r->id, kind: $r->kind, name: $r->name, phone: $r->phone, email: $r->email, country: $r->country, timezone: $r->timezone,
            locale: $r->locale, subjectId: $r->subject_id, forChild: $r->for_child, childName: $r->child_name, childBirthYear: $r->child_birth_year,
            subjectIds: array_values(array_map('strval', $r->subject_ids)), yearsTeaching: $r->years_teaching, message: $r->message,
            utmSource: $r->utm_source, utmMedium: $r->utm_medium, utmCampaign: $r->utm_campaign, status: $r->status, leadId: $r->lead_id,
        );
    }

    public function linkLead(string $requestId, string $leadId): void
    {
        JoinRequest::query()->whereKey($requestId)->update(['lead_id' => $leadId, 'updated_at' => now()]);
    }
}
