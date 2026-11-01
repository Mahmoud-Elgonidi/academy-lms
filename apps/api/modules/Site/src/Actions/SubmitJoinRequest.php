<?php

namespace Modules\Site\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Http\FormToken;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\Timezones;
use Modules\Site\Contracts\Events\JoinRequested;
use Modules\Site\Models\JoinRequest;
use Modules\Site\Models\SiteSettings;

/**
 * Someone asks from the academy's page: a free trial lesson (for themself or
 * their child) or to teach there. Who to talk to is a name and a mobile
 * number; the rest helps the academy answer. Staff hear of it, and the CRM
 * makes a lead of a trial (JoinRequested, after commit). A form a machine
 * sent (the hidden field filled, or no fresh token: FormToken) is answered
 * the same and kept nowhere; the same number asking the same again within a
 * week is the same request. A trial says its subject, or asks the academy to
 * help choose (the academy's default trial subject, else its staff choose),
 * and may say when the family is free (days and hours on their clock) and
 * the teacher they would rather have. A family that books its own trial
 * with a code to its number comes through record() (the code proved them).
 *
 * Plain loops and no collection closures: the public controller calls it,
 * and Scramble's inference of those breaks other endpoints.
 */
final class SubmitJoinRequest
{
    public const FORM = 'academy-join';

    private const SAME_FOR_DAYS = 7;

    private const FIELDS = ['kind', 'name', 'phone', 'email', 'country', 'timezone', 'subject_id', 'for_child', 'child_name', 'child_birth_year', 'subject_ids', 'years_teaching', 'message',
        'preferred_days', 'preferred_from', 'preferred_to', 'teacher_gender', 'help_choose'];

    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * @param  array<string, mixed>  $data  kind, name, phone, email, country, timezone, subject_id, help_choose, for_child, child_name, child_birth_year,
     *                                      preferred_days, preferred_from, preferred_to, teacher_gender, subject_ids, years_teaching, message,
     *                                      utm_source, utm_medium, utm_campaign, form_token, website (left empty by people)
     * @return ?JoinRequest the request kept (or the same one already waiting); null for a machine's form
     *
     * @throws ValidationException
     */
    public function handle(array $data, string $locale): ?JoinRequest
    {
        $token = $data['form_token'] ?? null;
        if (filled($data['website'] ?? null) || ! FormToken::passes(self::FORM, is_string($token) ? $token : null)) {
            return null;
        }

        return $this->record($data, $locale);
    }

    /**
     * Keeps a request a person sent (handle() checked the form; a family booking
     * its own trial proved its number with a code).
     *
     * @param  array<string, mixed>  $data  as handle()
     *
     * @throws ValidationException
     */
    public function record(array $data, string $locale): JoinRequest
    {
        $kind = is_string($data['kind'] ?? null) ? $data['kind'] : '';
        $trial = $kind === 'trial';
        $forChild = $trial && filter_var($data['for_child'] ?? false, FILTER_VALIDATE_BOOL);
        $subjects = [];
        if (! $trial && is_array($data['subject_ids'] ?? null)) {
            foreach ($data['subject_ids'] as $id) {
                if (is_string($id) && $id !== '' && ! in_array($id, $subjects, true)) {
                    $subjects[] = $id;
                }
            }
        }
        $helpChoose = $trial && filter_var($data['help_choose'] ?? false, FILTER_VALIDATE_BOOL);
        $days = [];
        if ($trial && is_array($data['preferred_days'] ?? null)) {
            foreach ($data['preferred_days'] as $day) {
                if (is_numeric($day) && ! in_array((int) $day, $days, true)) {
                    $days[] = (int) $day;
                }
            }
            sort($days);
        }
        $input = [
            'kind' => $kind,
            'name' => $this->text($data['name'] ?? null),
            'phone' => $this->phone($data['phone'] ?? null),
            'email' => ($email = $this->text($data['email'] ?? null)) !== null ? strtolower($email) : null,
            'country' => ($country = $this->text($data['country'] ?? null)) !== null ? strtoupper($country) : null,
            'timezone' => $this->text($data['timezone'] ?? null),
            'subject_id' => $trial && ! $helpChoose ? $this->text($data['subject_id'] ?? null) : null,
            'help_choose' => $helpChoose,
            'preferred_days' => $days,
            'preferred_from' => $trial && is_numeric($data['preferred_from'] ?? null) ? (int) $data['preferred_from'] : null,
            'preferred_to' => $trial && is_numeric($data['preferred_to'] ?? null) ? (int) $data['preferred_to'] : null,
            'teacher_gender' => $trial ? $this->text($data['teacher_gender'] ?? null) : null,
            'for_child' => $forChild,
            'child_name' => $forChild ? $this->text($data['child_name'] ?? null) : null,
            'child_birth_year' => $forChild && filled($data['child_birth_year'] ?? null) ? $data['child_birth_year'] : null,
            'subject_ids' => $subjects,
            'years_teaching' => ! $trial && filled($data['years_teaching'] ?? null) ? $data['years_teaching'] : null,
            'message' => $this->text($data['message'] ?? null),
        ];
        $year = CarbonImmutable::now()->year;
        $attributes = [];
        foreach (self::FIELDS as $field) {
            $attributes[$field] = (string) __('site::site.request.fields.'.$field);
        }
        $attributes['subject_ids.*'] = (string) __('site::site.request.fields.subject_ids.*');
        $attributes['preferred_days.*'] = (string) __('site::site.request.fields.preferred_days');
        $valid = Validator::make($input, [
            'kind' => ['required', Rule::in(JoinRequest::KINDS)],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'regex:/^\+[1-9]\d{6,14}$/'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'country' => ['nullable', 'regex:/^[A-Z]{2}$/'],
            'timezone' => ['nullable', Rule::in(Timezones::all())],
            'subject_id' => ['bail', Rule::requiredIf($trial && ! $helpChoose), 'nullable', 'uuid', Rule::exists('subjects', 'id')],
            'help_choose' => ['boolean'],
            'preferred_days' => ['array', 'max:7'],
            'preferred_days.*' => ['integer', 'between:1,7'],
            'preferred_from' => ['nullable', 'integer', 'between:0,23'],
            'preferred_to' => ['nullable', 'integer', 'between:1,24', $input['preferred_from'] !== null ? 'gt:preferred_from' : 'nullable'],
            'teacher_gender' => ['nullable', Rule::in(['female', 'male'])],
            'for_child' => ['boolean'],
            'child_name' => ['nullable', Rule::requiredIf($forChild), 'string', 'max:60'],
            'child_birth_year' => ['nullable', 'integer', 'between:'.($year - 18).','.($year - 3)],
            'subject_ids' => ['array', 'max:10'],
            'subject_ids.*' => ['bail', 'uuid', Rule::exists('subjects', 'id')],
            'years_teaching' => ['nullable', 'integer', 'between:0,60'],
            'message' => ['nullable', 'string', 'max:1000'],
        ], ['phone.regex' => __('site::site.errors.phone')], $attributes)->validate();

        $same = JoinRequest::query()->where('phone', $valid['phone'])->where('kind', $valid['kind'])->where('status', 'new')
            ->where('created_at', '>=', CarbonImmutable::now()->subDays(self::SAME_FOR_DAYS))->latest()->first();
        if ($same !== null) {
            return $same;
        }

        $academyId = $this->tenants->require()->id;
        // "Help me choose": the academy's subject for such trials, else its staff choose one with the family.
        $subjectId = $valid['subject_id'] ?? null;
        if ($helpChoose) {
            $subjectId = SiteSettings::current()->default_trial_subject_id;
        }

        return DB::transaction(function () use ($valid, $data, $locale, $academyId, $subjectId) {
            $request = JoinRequest::query()->create([
                'kind' => $valid['kind'],
                'name' => $valid['name'],
                'phone' => $valid['phone'],
                'email' => $valid['email'] ?? null,
                'country' => $valid['country'] ?? null,
                'timezone' => $valid['timezone'] ?? null,
                'locale' => $locale === 'en' ? 'en' : 'ar',
                'subject_id' => $subjectId,
                'help_choose' => (bool) ($valid['help_choose'] ?? false),
                'preferred_days' => array_values($valid['preferred_days'] ?? []),
                'preferred_from' => isset($valid['preferred_from']) ? (int) $valid['preferred_from'] : null,
                'preferred_to' => isset($valid['preferred_to']) ? (int) $valid['preferred_to'] : null,
                'teacher_gender' => $valid['teacher_gender'] ?? null,
                'for_child' => (bool) ($valid['for_child'] ?? false),
                'child_name' => $valid['child_name'] ?? null,
                'child_birth_year' => isset($valid['child_birth_year']) ? (int) $valid['child_birth_year'] : null,
                'subject_ids' => array_values($valid['subject_ids'] ?? []),
                'years_teaching' => isset($valid['years_teaching']) ? (int) $valid['years_teaching'] : null,
                'message' => $valid['message'] ?? null,
                'utm_source' => $this->utm($data['utm_source'] ?? null),
                'utm_medium' => $this->utm($data['utm_medium'] ?? null),
                'utm_campaign' => $this->utm($data['utm_campaign'] ?? null),
            ]);
            DB::afterCommit(fn () => event(new JoinRequested($academyId, $request->id, $request->kind)));

            return $request;
        });
    }

    /** Text as typed, trimmed, without what cannot be read; null when empty. */
    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $text = trim((string) preg_replace('/[^\P{C}\n]/u', '', str_replace(["\r\n", "\r"], "\n", (string) $value)));

        return $text === '' ? null : $text;
    }

    /** "00966 50-123 4567" → "+966501234567"; anything else is left for the check to refuse. */
    private function phone(mixed $value): ?string
    {
        $phone = (string) preg_replace('/[\s\-().]/', '', (string) $this->text($value));
        if (str_starts_with($phone, '00')) {
            $phone = '+'.substr($phone, 2);
        }

        return $phone === '' ? null : $phone;
    }

    private function utm(mixed $value): ?string
    {
        $text = is_string($value) ? trim((string) preg_replace('/[^\w.\-+ ]/u', '', $value)) : '';

        return $text === '' ? null : mb_substr($text, 0, 100);
    }
}
