<?php

namespace Modules\Platform\Actions\Site;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Models\Site\SignupRequest;
use Modules\Platform\Site\NewSignupRequest;
use Modules\SharedKernel\Http\FormToken;

/**
 * An academy asks to join from the platform's page: its name, who to talk
 * to, roughly how many students, the plan it looked at. The platform's owners
 * hear of it by email; the console's "Signup requests" lists it, and the
 * academy is made from it there. A form a machine sent (the hidden field
 * filled, or no fresh token: FormToken) is answered the same and kept
 * nowhere; the same address asking again within a week is the same request.
 */
final class SubmitSignupRequest
{
    public const FORM = 'platform-signup';

    private const SAME_FOR_DAYS = 7;

    /**
     * @param  array<string, mixed>  $data  academy_name, contact_name, email, phone, country, students, plan, message, utm_source, utm_medium, utm_campaign, form_token, website (left empty by people)
     *
     * @throws ValidationException
     */
    public function handle(array $data, string $locale): ?SignupRequest
    {
        if (filled($data['website'] ?? null) || ! FormToken::passes(self::FORM, is_string($data['form_token'] ?? null) ? $data['form_token'] : null)) {
            return null;
        }

        $phone = preg_replace('/[\s\-().]/', '', (string) ($data['phone'] ?? ''));
        $phone = str_starts_with((string) $phone, '00') ? '+'.substr((string) $phone, 2) : $phone;
        $input = [
            'academy_name' => trim((string) ($data['academy_name'] ?? '')),
            'contact_name' => trim((string) ($data['contact_name'] ?? '')),
            'email' => strtolower(trim((string) ($data['email'] ?? ''))),
            'phone' => $phone !== '' ? $phone : null,
            'country' => filled($data['country'] ?? null) ? strtoupper(trim((string) $data['country'])) : null,
            'students' => filled($data['students'] ?? null) ? (string) $data['students'] : null,
            'plan' => filled($data['plan'] ?? null) ? strtolower(trim((string) $data['plan'])) : null,
            'message' => filled($data['message'] ?? null) ? trim((string) $data['message']) : null,
            'utm_source' => $this->utm($data['utm_source'] ?? null),
            'utm_medium' => $this->utm($data['utm_medium'] ?? null),
            'utm_campaign' => $this->utm($data['utm_campaign'] ?? null),
        ];
        $l = 'platform::site.request.fields.';
        $valid = Validator::make($input, [
            'academy_name' => ['required', 'string', 'min:2', 'max:120'],
            'contact_name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'regex:/^\+[1-9]\d{6,14}$/'],
            'country' => ['nullable', 'regex:/^[A-Z]{2}$/'],
            'students' => ['nullable', Rule::in(SignupRequest::STUDENTS)],
            'plan' => ['nullable', Rule::in(SaasPlan::query()->offered()->where('shown_on_site', true)->pluck('code')->all())],
            'message' => ['nullable', 'string', 'max:1000'],
        ], [], collect(['academy_name', 'contact_name', 'email', 'phone', 'country', 'students', 'plan', 'message'])->mapWithKeys(fn ($f) => [$f => __($l.$f)])->all())->validate();

        $same = SignupRequest::query()->where('email', $valid['email'])->where('status', 'new')
            ->where('created_at', '>=', now()->subDays(self::SAME_FOR_DAYS))->latest()->first();
        if ($same !== null) {
            return $same;
        }

        $request = DB::connection('pgsql_platform')->transaction(fn () => SignupRequest::query()->create([
            'academy_name' => $valid['academy_name'],
            'contact_name' => $valid['contact_name'],
            'email' => $valid['email'],
            'phone' => $valid['phone'] ?? null,
            'country' => $valid['country'] ?? null,
            'students' => $valid['students'] ?? null,
            'plan_code' => $valid['plan'] ?? null,
            'message' => $valid['message'] ?? null,
            'locale' => $locale === 'en' ? 'en' : 'ar',
            'utm_source' => $input['utm_source'],
            'utm_medium' => $input['utm_medium'],
            'utm_campaign' => $input['utm_campaign'],
        ]));

        $owners = PlatformAdmin::query()->where('is_owner', true)->where('is_active', true)->pluck('email')->all();
        if ($owners !== []) {
            Mail::to($owners)->queue(new NewSignupRequest($request->only([
                'id', 'academy_name', 'contact_name', 'email', 'phone', 'country', 'students', 'plan_code', 'message', 'locale', 'utm_source', 'utm_campaign',
            ])));
        }

        return $request;
    }

    private function utm(mixed $value): ?string
    {
        $text = is_string($value) ? trim((string) preg_replace('/[^\w.\-+ ]/u', '', $value)) : '';

        return $text === '' ? null : mb_substr($text, 0, 100);
    }
}
