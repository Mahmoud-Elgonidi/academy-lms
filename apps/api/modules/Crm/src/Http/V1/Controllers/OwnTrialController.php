<?php

namespace Modules\Crm\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Crm\Actions\BookOwnTrial;
use Modules\Identity\Contracts\IdentityApi;
use Modules\People\Contracts\AgeGroup;
use Modules\Scheduling\Contracts\DTOs\TrialSlot;
use Modules\Scheduling\Contracts\DTOs\TrialSlotSearch;
use Modules\Scheduling\Contracts\TrialBooking;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;
use Modules\Site\Contracts\SiteApi;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A family books its own free trial from the academy's page, before anyone
 * signs in (the family's journey, docs/architecture/02 section 27): the
 * times on offer, a code to its number, then the booking with the code; or,
 * from the "pick a time" link of the instant reply, the request's details
 * and the booking with no code (the link went to that number). Not found
 * while the academy's page is off or its staff book the trials. Lists are
 * shaped with plain loops: Scramble's inference of collection closures
 * breaks other endpoints.
 */
final class OwnTrialController
{
    private const REQUEST_FIELDS = [
        'name', 'phone', 'email', 'country', 'timezone', 'subject_id', 'help_choose', 'for_child', 'child_name', 'child_birth_year',
        'preferred_days', 'preferred_from', 'preferred_to', 'teacher_gender', 'message', 'utm_source', 'utm_medium', 'utm_campaign',
    ];

    public function __construct(
        private readonly SiteApi $site,
        private readonly LocalTimePresenter $time,
        private readonly ViewerTimezone $viewer,
    ) {}

    /**
     * Times a family may book a free trial at.
     *
     * Starts on a half-hour grid of the family's clock (timezone, else X-Timezone, else the academy's), from three hours ahead,
     * over 14 days from `from` (a date; default today), with teachers who take trials, teach the subject and the age
     * (age_group, or the child's birth_year; a child only with teachers cleared for minors) and are of teacher_gender when given.
     */
    public function slots(Request $request, TrialBooking $trials): JsonResponse
    {
        $this->ensureFamilyBooks();
        $year = CarbonImmutable::now()->year;
        $valid = $request->validate([
            'subject_id' => ['required', 'uuid', Rule::exists('subjects', 'id')],
            'age_group' => ['nullable', Rule::enum(AgeGroup::class)],
            'birth_year' => ['nullable', 'integer', 'between:'.($year - 90).','.($year - 3)],
            'teacher_gender' => ['nullable', Rule::in(TrialSlotSearch::GENDERS)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'timezone' => ['nullable', Rule::in(Timezones::all())],
        ]);
        $zone = $this->zone($valid['timezone'] ?? null);
        $age = isset($valid['birth_year']) ? $year - (int) $valid['birth_year'] : null;
        $group = isset($valid['age_group']) ? AgeGroup::from($valid['age_group']) : AgeGroup::forAge($age);
        $found = $trials->slots(new TrialSlotSearch(
            subjectId: $valid['subject_id'],
            timezone: $zone,
            ageGroup: $group?->value,
            forMinor: ($age !== null && $age < 18) || in_array($group, [AgeGroup::Kids, AgeGroup::Middle, AgeGroup::Teen], true),
            teacherGender: $valid['teacher_gender'] ?? null,
            from: isset($valid['from']) ? CarbonImmutable::parse($valid['from'], $zone) : null,
            noticeHours: $this->site->trialSettings()->noticeHours,
        ));

        return response()->json(['data' => $this->presentSlots($found, $zone)])->header('Cache-Control', 'no-store');
    }

    /**
     * Send a code to the family's mobile number.
     *
     * By WhatsApp (or a text, channel) before booking a free trial. The same answer whether or not the number is known here.
     */
    public function code(Request $request, IdentityApi $identity): JsonResponse
    {
        $this->ensureFamilyBooks();
        $valid = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'channel' => ['nullable', Rule::in(['whatsapp', 'sms'])],
        ]);
        $sent = $identity->sendPhoneCode($valid['phone'], app()->getLocale(), $valid['channel'] ?? null, $request->ip());

        return response()->json(['data' => [
            /** Sent back with the code as challenge_id. */
            'challenge_id' => $sent->challengeId,
            'channel' => $sent->channel,
            /** The number, masked. */
            'sent_to' => $sent->sentTo,
            'expires_at' => $this->time->present($sent->expiresAt),
            /** Seconds before another code may be asked for. */
            'resend_after' => $sent->resendAfter,
        ]], 202);
    }

    /**
     * Book the free trial with the code.
     *
     * The request's details as the page's form sends them (name, phone, subject_id or help_choose, for_child, child_name, child_birth_year,
     * preferred_days, preferred_from, preferred_to, teacher_gender, ...), the code sent to that phone, and starts_at, one of the times `slots` offered.
     * A message brings the time, the link to join, and links to change the time or cancel.
     */
    public function store(Request $request, BookOwnTrial $book): JsonResponse
    {
        $valid = $request->validate([
            'challenge_id' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'max:12'],
            'starts_at' => ['required', 'date'],
        ]);
        $sessionId = $book->withCode($request->only(self::REQUEST_FIELDS), $valid['challenge_id'], $valid['code'],
            CarbonImmutable::parse($valid['starts_at'])->utc(), app()->getLocale());

        return response()->json(['data' => $this->booked($sessionId, $request->input('timezone'))], 201);
    }

    /**
     * The free trial request a family's "pick a time" link names.
     *
     * What they asked for, to show the times for it (site/trial-slots) and book one with no code. Not found once a time is booked.
     */
    public function pick(string $token): JsonResponse
    {
        $this->ensureFamilyBooks();
        $request = $this->site->requestForPick($token) ?? throw new NotFoundHttpException;
        $subject = $request->subjectId !== null ? DB::table('subjects')->where('id', $request->subjectId)->first(['id', 'name_ar', 'name_en']) : null;
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';

        return response()->json(['data' => [
            /** The name they gave. */
            'name' => $request->name,
            /** The subject, or null when the academy chooses it with them (no time can be picked then). */
            'subject' => $subject === null ? null : ['id' => (string) $subject->id, 'name' => (string) ($locale === 'en' ? $subject->name_en : $subject->name_ar)],
            'for_child' => $request->forChild,
            'child_name' => $request->childName,
            'child_birth_year' => $request->childBirthYear,
            /** Their clock (IANA), or null. */
            'timezone' => $request->timezone,
            /** ISO weekdays that suit them. */
            'preferred_days' => $request->preferredDays,
            'preferred_from' => $request->preferredFrom,
            'preferred_to' => $request->preferredTo,
            /** female or male: the teacher they would rather have, or null. */
            'teacher_gender' => $request->teacherGender,
        ]])->header('Cache-Control', 'no-store');
    }

    /**
     * Book the free trial from the "pick a time" link.
     *
     * starts_at: one of the times `slots` offered for the request. No code: the link went to the number they gave.
     */
    public function pickBook(Request $request, string $token, BookOwnTrial $book): JsonResponse
    {
        $valid = $request->validate(['starts_at' => ['required', 'date'], 'timezone' => ['nullable', Rule::in(Timezones::all())]]);
        $sessionId = $book->fromPick($token, CarbonImmutable::parse($valid['starts_at'])->utc());

        return response()->json(['data' => $this->booked($sessionId, $valid['timezone'] ?? null)], 201);
    }

    private function ensureFamilyBooks(): void
    {
        if (! $this->site->trialSettings()->familyBooks) {
            throw new NotFoundHttpException;
        }
    }

    private function zone(mixed $asked): string
    {
        return is_string($asked) && in_array($asked, Timezones::all(), true) ? $asked : $this->viewer->effective();
    }

    /**
     * @param  list<TrialSlot>  $slots
     * @return list<array{starts_at: array<string, string>|null}>
     */
    private function presentSlots(array $slots, string $zone): array
    {
        $out = [];
        foreach ($slots as $slot) {
            $out[] = ['starts_at' => $this->time->present($slot->startsAt, $zone)];
        }

        return $out;
    }

    /** @return array{starts_at: array<string, string>|null, subject: string, teacher: string, minutes: int} */
    private function booked(string $sessionId, mixed $timezone): array
    {
        $lesson = DB::table('sessions as s')
            ->join('subjects as su', fn ($j) => $j->on('su.academy_id', '=', 's.academy_id')->on('su.id', '=', 's.subject_id'))
            ->leftJoin('users as u', 'u.id', '=', 's.teacher_id')
            ->where('s.id', $sessionId)->first(['s.starts_at', 's.ends_at', 'su.name_ar', 'su.name_en', 'u.name as teacher']);
        $starts = $lesson !== null ? CarbonImmutable::parse($lesson->starts_at) : null;

        return [
            'starts_at' => $this->time->present($starts, $this->zone($timezone)),
            'subject' => (string) (app()->getLocale() === 'en' ? ($lesson->name_en ?? '') : ($lesson->name_ar ?? '')),
            /** The teacher's first name only (with a title before it, as "أ. منى"). */
            'teacher' => preg_match('/^(?:\S+\.\s+)?\S+/u', trim((string) ($lesson->teacher ?? '')), $first) === 1 ? $first[0] : '',
            /** How long the lesson is, in minutes. */
            'minutes' => $starts !== null ? (int) $starts->diffInMinutes(CarbonImmutable::parse($lesson->ends_at), true) : 0,
        ];
    }
}
