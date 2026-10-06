<?php

namespace Modules\Crm\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Crm\Enums\LeadSource;
use Modules\SharedKernel\Time\Timezones;

/** A lead's fields as the form and the API send them: cleaned, then checked. */
final class LeadFields
{
    public const FIELDS = [
        'contact_name', 'phone_e164', 'email', 'country', 'timezone', 'locale', 'for_child', 'learner_name', 'learner_birth_year',
        'subject_id', 'preferred_days', 'preferred_from', 'preferred_to', 'source', 'utm_source', 'utm_medium', 'utm_campaign', 'notes',
    ];

    /** "00966 50-123 4567" → "+966501234567"; anything else is left for the check to refuse. */
    public static function phone(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $phone = preg_replace('/[\s\-().]/', '', trim($raw));

        return str_starts_with($phone, '00') ? '+'.substr($phone, 2) : $phone;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> the fields to save
     */
    public static function validate(array $data): array
    {
        $data = array_intersect_key($data, array_flip(self::FIELDS));
        $data['phone_e164'] = self::phone($data['phone_e164'] ?? null);
        $data['for_child'] = (bool) ($data['for_child'] ?? false);
        $data['locale'] ??= 'ar';
        $data['preferred_days'] = array_values(array_map('intval', $data['preferred_days'] ?? []));
        foreach (['email', 'country', 'learner_name', 'learner_birth_year', 'subject_id', 'preferred_from', 'preferred_to', 'utm_source', 'utm_medium', 'utm_campaign', 'notes'] as $optional) {
            $data[$optional] = blank($data[$optional] ?? null) ? null : $data[$optional];
        }
        if (! $data['for_child']) {
            [$data['learner_name'], $data['learner_birth_year']] = [null, null];
        }
        $year = CarbonImmutable::now()->year;

        $valid = Validator::make($data, [
            'contact_name' => ['required', 'string', 'max:120'],
            'phone_e164' => ['required', 'regex:/^\+[1-9]\d{6,14}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'country' => ['nullable', Rule::in(array_keys(Countries::ZONES))],
            'timezone' => ['required', Rule::in(Timezones::all())],
            'locale' => ['required', Rule::in(['ar', 'en'])],
            'for_child' => ['boolean'],
            'learner_name' => ['nullable', 'required_if:for_child,true', 'string', 'max:120'],
            'learner_birth_year' => ['nullable', 'integer', 'between:'.($year - 90).','.($year - 3)],
            'subject_id' => ['nullable', Rule::exists('subjects', 'id')],
            'preferred_days' => ['array'],
            'preferred_days.*' => ['integer', 'between:1,7'],
            'preferred_from' => ['nullable', 'integer', 'between:0,23'],
            'preferred_to' => ['nullable', 'integer', 'between:1,24', 'gt:preferred_from'],
            'source' => ['required', Rule::enum(LeadSource::class)],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], __('crm::leads.fields'))->validate();

        return $valid;
    }
}
