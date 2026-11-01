<?php

namespace Modules\Site\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\SiteSettings;

/**
 * The owner or an admin saves the academy's page: on or off, what it says
 * (Domain\SiteContent, cleaned and held to its lengths), and its free trials:
 * who books one (the family itself or the academy's staff), how many hours
 * the academy promises to answer within, and the subject of a trial whose
 * family asked for help choosing. It shows at once. Audited with whether it
 * is on, the sections shown and the trial settings, never the words.
 */
final class SaveSiteSettings
{
    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @param  array<string, mixed>  $data  enabled, the content (hero, features, testimonials, faqs, sections, whatsapp, seo),
     *                                      and the trials: trial_booking (family | staff), response_hours (1–72), default_trial_subject_id
     *
     * @throws ValidationException
     */
    public function handle(array $data): SiteSettings
    {
        if (! Gate::allows(Ability::ManageSite->value)) {
            throw new AuthorizationException;
        }
        $content = SiteContent::normalize($data);
        $enabled = filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOL);
        $current = SiteSettings::current();
        $trials = Validator::make([
            'trial_booking' => $data['trial_booking'] ?? $current->trial_booking,
            'response_hours' => $data['response_hours'] ?? $current->response_hours,
            'trial_notice_hours' => $data['trial_notice_hours'] ?? $current->trial_notice_hours,
            'default_trial_subject_id' => array_key_exists('default_trial_subject_id', $data) ? ($data['default_trial_subject_id'] ?: null) : $current->default_trial_subject_id,
        ], [
            'trial_booking' => ['required', Rule::in(SiteSettings::TRIAL_BOOKING)],
            'response_hours' => ['required', 'integer', 'between:1,72'],
            'trial_notice_hours' => ['required', 'integer', 'between:1,72'],
            'default_trial_subject_id' => ['bail', 'nullable', 'uuid', Rule::exists('subjects', 'id')],
        ], [], [
            'trial_booking' => __('site::site.settings.trials.booking'),
            'response_hours' => __('site::site.settings.trials.response_hours'),
            'trial_notice_hours' => __('site::site.settings.trials.notice_hours'),
            'default_trial_subject_id' => __('site::site.settings.trials.default_subject'),
        ])->validate();

        return DB::transaction(function () use ($content, $enabled, $trials) {
            $row = SiteSettings::query()->lockForUpdate()->first() ?? new SiteSettings;
            $row->fill(['enabled' => $enabled, 'content' => $content, 'updated_by' => auth()->id()] + [
                'trial_booking' => $trials['trial_booking'],
                'response_hours' => (int) $trials['response_hours'],
                'trial_notice_hours' => (int) $trials['trial_notice_hours'],
                'default_trial_subject_id' => $trials['default_trial_subject_id'] ?? null,
            ])->save();
            $this->audit->record('site.settings_saved', 'site_settings', null, [
                'state' => $enabled ? 'on' : 'off', 'shown' => $content['sections'],
                'trial_booking' => $trials['trial_booking'], 'response_hours' => (int) $trials['response_hours'],
                'trial_notice_hours' => (int) $trials['trial_notice_hours'],
                'default_trial_subject' => ($trials['default_trial_subject_id'] ?? null) !== null,
            ]);

            return $row;
        });
    }
}
