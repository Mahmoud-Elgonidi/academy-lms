<?php

namespace Modules\Site;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Site\Actions\SubmitJoinRequest;
use Modules\Site\Contracts\DTOs\JoinRequestData;
use Modules\Site\Contracts\DTOs\TrialSettings;
use Modules\Site\Contracts\SiteApi;
use Modules\Site\Models\JoinRequest;
use Modules\Site\Models\SiteSettings;
use Modules\Site\Support\PickLinks;

final class SiteService implements SiteApi
{
    /** A lead's stage => what the request it came from reads (one status). */
    public const STATUS_OF_STAGE = [
        'new' => 'new', 'contacted' => 'contacted', 'trial_booked' => 'contacted', 'attended' => 'contacted', 'assessed' => 'contacted',
        'won' => 'accepted', 'lost' => 'declined',
    ];

    public function joinRequest(string $id): ?JoinRequestData
    {
        $r = Str::isUuid($id) ? JoinRequest::query()->find($id) : null;

        return $r === null ? null : self::data($r);
    }

    public function linkLead(string $requestId, string $leadId): void
    {
        JoinRequest::query()->whereKey($requestId)->update(['lead_id' => $leadId, 'lead_failed_at' => null, 'lead_failure' => null, 'updated_at' => now()]);
    }

    public function trialSettings(): TrialSettings
    {
        $settings = SiteSettings::current();

        return new TrialSettings(
            pageOn: $settings->exists && $settings->enabled,
            familyBooks: $settings->familyBooks(),
            responseHours: (int) $settings->response_hours,
            defaultSubjectId: $settings->default_trial_subject_id,
            noticeHours: (int) $settings->trial_notice_hours,
        );
    }

    public function recordTrialRequest(array $data, string $locale): string
    {
        return app(SubmitJoinRequest::class)->record(['kind' => 'trial'] + $data, $locale)->id;
    }

    public function trialBooked(string $requestId, string $sessionId): void
    {
        JoinRequest::query()->whereKey($requestId)->update(['session_id' => $sessionId, 'updated_at' => now()]);
    }

    public function requestForPick(string $token): ?JoinRequestData
    {
        $request = PickLinks::request($token);

        return $request === null ? null : self::data($request);
    }

    public function leadMoved(string $leadId, string $stage): void
    {
        $status = self::STATUS_OF_STAGE[$stage] ?? null;
        if ($status === null) {
            return;
        }
        DB::transaction(fn () => JoinRequest::query()->where('lead_id', $leadId)->where('kind', 'trial')->where('status', '!=', $status)
            ->update(['status' => $status, 'updated_at' => now()]));
    }

    public function leadFailed(string $requestId, string $reason): void
    {
        JoinRequest::query()->whereKey($requestId)->whereNull('lead_id')
            ->update(['lead_failed_at' => now(), 'lead_failure' => $reason === 'invalid' ? 'invalid' : 'error', 'updated_at' => now()]);
    }

    private static function data(JoinRequest $r): JoinRequestData
    {
        $days = [];
        foreach ($r->preferred_days as $day) {
            $days[] = (int) $day;
        }

        return new JoinRequestData(
            id: $r->id, kind: $r->kind, name: $r->name, phone: $r->phone, email: $r->email, country: $r->country, timezone: $r->timezone,
            locale: $r->locale, subjectId: $r->subject_id, forChild: $r->for_child, childName: $r->child_name, childBirthYear: $r->child_birth_year,
            subjectIds: array_values(array_map('strval', $r->subject_ids)), yearsTeaching: $r->years_teaching, message: $r->message,
            utmSource: $r->utm_source, utmMedium: $r->utm_medium, utmCampaign: $r->utm_campaign, status: $r->status, leadId: $r->lead_id,
            preferredDays: $days, preferredFrom: $r->preferred_from, preferredTo: $r->preferred_to, teacherGender: $r->teacher_gender,
            helpChoose: $r->help_choose, sessionId: $r->session_id,
        );
    }
}
