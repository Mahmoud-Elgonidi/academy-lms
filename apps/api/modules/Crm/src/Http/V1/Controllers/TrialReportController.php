<?php

namespace Modules\Crm\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Crm\Actions\BookNextTrial;
use Modules\Crm\Domain\NextTrialRules;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Queries\TrialReport;
use Modules\Crm\Support\NextTrials;
use Modules\Crm\Support\ReportLinks;
use Modules\Scheduling\Contracts\DTOs\TrialSlot;
use Modules\SharedKernel\Http\LinkToken;
use Modules\SharedKernel\Http\ProblemException;
use Modules\SharedKernel\Time\LocalTimePresenter;
use Modules\SharedKernel\Time\Timezones;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A family's own links after a free trial, before anyone signs in (docs/
 * architecture/02, section 27, J2): the trial's report, and from it the free
 * second trial with another teacher; and "book again" after a trial that
 * did not take place. The token is the proof (it went to the family's
 * number): another academy's, another purpose's or a forged one is not
 * found, one whose time passed has ended (410), and nothing here needs the
 * academy's page to be on (only booking does, through the rules). Times are
 * on the family's clock (timezone, else X-Timezone, else the lead's).
 */
final class TrialReportController
{
    public function __construct(
        private readonly TrialReport $reports,
        private readonly LocalTimePresenter $time,
    ) {}

    /**
     * A free trial's report, from the family's link.
     *
     * The level, strengths, what to work on and the teacher's words; the recommended plan with its price in the family's region;
     * the academy's offer while it is open (its end and the price after it); a course already started; and the free second trial
     * with another teacher while the academy gives one. 404 for a link that is not this academy's or names no report; 410 once it ended.
     */
    public function report(Request $request, string $token): JsonResponse
    {
        $trial = $this->trial(ReportLinks::REPORT, $token);
        if (! TrialReport::ready($trial)) {
            throw new NotFoundHttpException;
        }

        return response()->json(['data' => $this->reports->present($trial, true, $this->zone($request, $trial))])->header('Cache-Control', 'no-store');
    }

    /**
     * Times for the free second trial, from the report's link.
     *
     * With any teacher but the first one, who teaches the subject and the learner's age, on the family's clock, over 14 days from `from`.
     * 422 with why it cannot be booked (the family's words: what to do instead).
     */
    public function reportSlots(Request $request, string $token, BookNextTrial $book): JsonResponse
    {
        $trial = $this->trial(ReportLinks::REPORT, $token);
        if (! TrialReport::ready($trial)) {
            throw new NotFoundHttpException;
        }

        return $this->slots($request, $trial, $book);
    }

    /**
     * Book the free second trial, from the report's link.
     *
     * starts_at: one of the times offered. No code: the link went to the family's number. The family hears the time and its links.
     */
    public function reportBook(Request $request, string $token, BookNextTrial $book): JsonResponse
    {
        $trial = $this->trial(ReportLinks::REPORT, $token);
        if (! TrialReport::ready($trial)) {
            throw new NotFoundHttpException;
        }

        return $this->book($request, $trial, $book);
    }

    /**
     * A trial that did not take place, from the family's "book again" link.
     *
     * Who, what and when it was, why (the learner missed it, or the teacher or the academy could not hold it: then it does not
     * count as the family's free trial), and whether a new time may be booked. 410 once a new time is booked or the link ended.
     */
    public function rebook(Request $request, string $token, NextTrials $next): JsonResponse
    {
        $trial = $this->rebookable($token);
        $lead = $trial->lead;
        $zone = $this->zone($request, $trial);
        $facts = $next->facts($trial, true);
        $why = $facts === null ? 'staff_books' : NextTrialRules::whyNot($facts);
        $subject = DB::table('sessions as s')
            ->join('subjects as su', fn ($j) => $j->on('su.academy_id', '=', 's.academy_id')->on('su.id', '=', 's.subject_id'))
            ->where('s.id', $trial->session_id)->first(['s.subject_id', 'su.name_ar', 'su.name_en']);

        return response()->json(['data' => [
            /** The learner's first name. */
            'learner' => TrialReport::firstName($lead->learnerName()),
            'subject' => ['id' => (string) ($subject->subject_id ?? ''), 'name' => (string) (app()->getLocale() === 'en' ? ($subject->name_en ?? '') : ($subject->name_ar ?? ''))],
            'was_at' => $this->time->present($trial->starts_at, $zone),
            /** student_absent (the learner missed it), teacher_absent or academy (not the family's doing: it does not count as their free trial). */
            'outcome' => $trial->outcome ?? 'student_absent',
            /** Whether a new time may be booked now. */
            'available' => $why === null,
            /** Why not, in the family's words (what to do instead); null when it may be booked. */
            'why_not' => $why === null ? null : __('crm::report.next.why.'.$why, ['count' => NextTrialRules::MAX_OPEN_PER_NUMBER]),
            /** Their clock (IANA), for the times. */
            'timezone' => $zone,
        ]])->header('Cache-Control', 'no-store');
    }

    /**
     * Times for a new trial, from the "book again" link.
     *
     * The subject's teachers for the learner, on the family's clock, over 14 days from `from`. 422 with why it cannot be booked.
     */
    public function rebookSlots(Request $request, string $token, BookNextTrial $book): JsonResponse
    {
        return $this->slots($request, $this->rebookable($token), $book);
    }

    /**
     * Book a new trial, from the "book again" link.
     *
     * starts_at: one of the times offered. No code: the link went to the family's number. The family hears the time and its links.
     */
    public function rebookBook(Request $request, string $token, BookNextTrial $book): JsonResponse
    {
        return $this->book($request, $this->rebookable($token), $book);
    }

    private function slots(Request $request, LeadTrial $trial, BookNextTrial $book): JsonResponse
    {
        $valid = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'timezone' => ['nullable', Rule::in(Timezones::all())]]);
        $zone = $this->zone($request, $trial);
        $found = $book->slots($trial, true, isset($valid['from']) ? CarbonImmutable::parse($valid['from'], $zone) : null, $zone);

        return response()->json(['data' => self::present($found, $zone, $this->time)])->header('Cache-Control', 'no-store');
    }

    private function book(Request $request, LeadTrial $trial, BookNextTrial $book): JsonResponse
    {
        $valid = $request->validate(['starts_at' => ['required', 'date'], 'timezone' => ['nullable', Rule::in(Timezones::all())]]);
        $sessionId = $book->handle($trial, CarbonImmutable::parse($valid['starts_at'])->utc(), true);

        return response()->json(['data' => self::booked($sessionId, $this->zone($request, $trial), $this->time)], 201);
    }

    /** The trial a token of this purpose names; 410 once its time passed, 404 when it names none here. */
    private function trial(string $purpose, string $token): LeadTrial
    {
        $id = LinkToken::read($purpose, $token);
        if ($id === null) {
            if (LinkToken::expired($purpose, $token)) {
                throw new ProblemException(410, 'link-expired', (string) __('crm::report.errors.link_expired'));
            }
            throw new NotFoundHttpException;
        }

        return LeadTrial::query()->with('lead')->find($id) ?? throw new NotFoundHttpException;
    }

    /** A trial the "book again" link names, while no new time was booked from it. */
    private function rebookable(string $token): LeadTrial
    {
        $trial = $this->trial(ReportLinks::REBOOK, $token);
        if (NextTrials::kindAfter($trial) !== 'rebook') {
            throw new NotFoundHttpException;
        }
        if (LeadTrial::query()->where('follows_trial_id', $trial->id)->exists()) {
            throw new ProblemException(410, 'link-expired', (string) __('crm::report.errors.rebooked'));
        }

        return $trial;
    }

    private function zone(Request $request, LeadTrial $trial): string
    {
        foreach ([$request->input('timezone'), $request->header('X-Timezone')] as $asked) {
            if (is_string($asked) && in_array($asked, Timezones::all(), true)) {
                return $asked;
            }
        }

        return $trial->lead->timezone;
    }

    /**
     * @param  list<TrialSlot>  $slots
     * @return list<array{starts_at: array<string, string>|null}>
     */
    public static function present(array $slots, string $zone, LocalTimePresenter $time): array
    {
        $out = [];
        foreach ($slots as $slot) {
            $out[] = ['starts_at' => $time->present($slot->startsAt, $zone)];
        }

        return $out;
    }

    /** @return array{id: string, starts_at: array<string, string>|null, subject: string, teacher: string, minutes: int} */
    public static function booked(string $sessionId, string $zone, LocalTimePresenter $time): array
    {
        $lesson = DB::table('sessions as s')
            ->join('subjects as su', fn ($j) => $j->on('su.academy_id', '=', 's.academy_id')->on('su.id', '=', 's.subject_id'))
            ->leftJoin('users as u', fn ($j) => $j->on('u.academy_id', '=', 's.academy_id')->on('u.id', '=', 's.teacher_id'))
            ->where('s.id', $sessionId)->first(['s.starts_at', 's.ends_at', 'su.name_ar', 'su.name_en', 'u.name as teacher']);
        $starts = $lesson !== null ? CarbonImmutable::parse($lesson->starts_at) : null;

        return [
            /** The new trial lesson's id. */
            'id' => $sessionId,
            'starts_at' => $time->present($starts, $zone),
            'subject' => (string) (app()->getLocale() === 'en' ? ($lesson->name_en ?? '') : ($lesson->name_ar ?? '')),
            /** The teacher's first name only (with a title before it, as "أ. منى"). */
            'teacher' => TrialReport::firstName((string) ($lesson->teacher ?? '')),
            /** How long the lesson is, in minutes. */
            'minutes' => $starts !== null ? (int) $starts->diffInMinutes(CarbonImmutable::parse($lesson->ends_at), true) : 0,
        ];
    }
}
