<?php

namespace Modules\Import\Importers;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Contracts\BillingApi;
use Modules\Billing\Contracts\DTOs\PlanOption;
use Modules\Import\Support\Cells;
use Modules\Import\Support\Directory;
use Modules\Import\Support\Field;
use Modules\Import\Support\ImportContext;
use Modules\Import\Support\Places;
use Modules\Import\Support\RowOutcome;
use Modules\Import\Support\Text;
use Modules\Scheduling\Contracts\DTOs\ExistingSchedule;
use Modules\Scheduling\Contracts\ScheduleImport;
use Modules\SharedKernel\Contact\Phone;

/**
 * Running schedules from a sheet, an enrollment a row: the student (by
 * their own or their guardian's number or email, and name), the teacher,
 * the subject, the lesson length, the weekly times (on the teacher's clock,
 * the student's, or a named zone), what is left of the block already paid,
 * and optionally the plan, so renewals carry on from the next block.
 * Scheduling makes the enrollment, its times and lessons ahead.
 */
final class SchedulesImporter implements Importer
{
    public function __construct(
        private readonly ScheduleImport $schedules,
        private readonly BillingApi $billing,
        private readonly Directory $directory,
    ) {}

    public function kind(): string
    {
        return 'schedules';
    }

    public function fields(): array
    {
        return [
            new Field('student_name', true, ['الطالب', 'اسم الطالب', 'الطالبة', 'student', 'student name']),
            new Field('contact', true, ['رقم التواصل', 'التواصل', 'الموبايل', 'الجوال', 'رقم ولي الأمر', 'موبايل ولي الأمر', 'الإيميل', 'الايميل', 'contact', 'phone', 'mobile', 'email', 'guardian phone']),
            new Field('teacher', true, ['المدرس', 'اسم المدرس', 'المعلم', 'teacher']),
            new Field('subject', true, ['المادة', 'subject']),
            new Field('duration', true, ['مدة الحصة', 'المدة', 'الدقايق', 'الدقائق', 'duration', 'minutes', 'lesson minutes']),
            new Field('times', true, ['المواعيد', 'الأيام', 'الميعاد', 'المواعيد الأسبوعية', 'الأيام والمواعيد', 'times', 'schedule', 'days', 'weekly times']),
            new Field('clock', false, ['توقيت المواعيد', 'بتوقيت', 'المواعيد بتوقيت', 'clock', 'times time zone', 'time zone']),
            new Field('start_date', false, ['يبدأ من', 'تاريخ البداية', 'البداية', 'start', 'start date', 'from']),
            new Field('remaining', false, ['الحصص المتبقية', 'المتبقي', 'الحصص الباقية', 'الرصيد', 'remaining', 'lessons left', 'remaining lessons']),
            new Field('valid_until', false, ['صالح لحد', 'الرصيد صالح لحد', 'صالح حتى', 'الرصيد صالح حتى', 'تاريخ الانتهاء', 'الانتهاء', 'valid until', 'expires', 'expiry']),
            new Field('plan', false, ['الباقة', 'الاشتراك', 'plan', 'package']),
            new Field('payer', false, ['اللي بيدفع', 'الدافع', 'رقم الدافع', 'payer']),
        ];
    }

    public function import(array $row, ImportContext $context): RowOutcome
    {
        $e = 'import::rows.errors.';
        $studentId = $this->student($row['student_name'], $row['contact'], $context);
        $teacherId = $this->directory->teacher($row['teacher'], $context->callingCode) ?? throw ValidationException::withMessages(['teacher' => __($e.'no_teacher', ['value' => $row['teacher']])]);
        $subjectId = $this->directory->subject($row['subject']) ?? throw ValidationException::withMessages(['subject' => __($e.'no_subject', ['value' => $row['subject']])]);
        $duration = Cells::minutes($row['duration']) ?? throw ValidationException::withMessages(['duration' => __($e.'number', ['value' => $row['duration']])]);
        $times = Cells::weeklyTimes($row['times']) ?? throw ValidationException::withMessages(['times' => __('scheduling::import.errors.times')]);
        $clock = match (Text::key($row['clock'])) {
            '', 'المدرس', 'المعلم', 'teacher' => null,
            'الطالب', 'student' => $this->directory->timezoneOf($studentId) ?? $context->timezone,
            default => Places::timezone($row['clock']) ?? throw ValidationException::withMessages(['clock' => __($e.'timezone', ['value' => $row['clock']])]),
        };
        $today = CarbonImmutable::now($context->timezone)->startOfDay();
        $start = $row['start_date'] === '' ? $today : (Cells::date($row['start_date']) ?? throw ValidationException::withMessages(['start_date' => __($e.'date', ['value' => $row['start_date']])]));
        $remaining = $row['remaining'] === '' ? 0 : (Cells::integer($row['remaining']) ?? throw ValidationException::withMessages(['remaining' => __($e.'number', ['value' => $row['remaining']])]));
        $validUntil = $row['valid_until'] === '' ? null : (Cells::date($row['valid_until']) ?? throw ValidationException::withMessages(['valid_until' => __($e.'date', ['value' => $row['valid_until']])]));
        $planId = $row['plan'] === '' ? null : (collect($this->billing->planOptions())->first(fn (PlanOption $p) => in_array(Text::key($row['plan']), array_map(Text::key(...), [$p->name, ...$p->names]), true))?->id
            ?? throw ValidationException::withMessages(['plan' => __($e.'no_plan', ['value' => $row['plan']])]));
        $payerId = null;
        if ($row['payer'] !== '') {
            $payer = str_contains($row['payer'], '@') ? $this->directory->byContact($row['payer'], null) : $this->directory->byContact(null, Phone::normalize($row['payer'], $context->callingCode));
            $payerId = $payer?->id ?? throw ValidationException::withMessages(['payer' => __($e.'no_person', ['value' => $row['payer']])]);
        }

        if ($this->schedules->exists($studentId, $teacherId, $subjectId)) {
            return new RowOutcome('skipped', [__('import::rows.notes.schedule_exists')], ['student' => $studentId]);
        }

        $brought = $this->schedules->bring(new ExistingSchedule(
            studentId: $studentId, teacherId: $teacherId, subjectId: $subjectId, durationMin: $duration, times: $times, clock: $clock,
            startDate: $start->max($today), remainingLessons: $remaining, creditExpiresOn: $validUntil, planId: $planId, payerId: $payerId, actorId: $context->actorId,
        ));

        $notes = [trans_choice('import::rows.notes.lessons', $brought->sessions, ['count' => $brought->sessions])];
        if ($remaining > 0) {
            $notes[] = trans_choice('import::rows.notes.balance', $remaining, ['count' => $remaining]);
        }
        if ($brought->skippedDates !== []) {
            $notes[] = __('import::rows.notes.skipped_dates', ['dates' => implode('، ', $brought->skippedDates)]);
        }

        return new RowOutcome('created', $notes, ['student' => $studentId, 'enrollment' => $brought->enrollmentId]);
    }

    private function student(string $name, string $contact, ImportContext $context): string
    {
        $e = 'import::rows.errors.';
        if ($name === '' || $contact === '') {
            throw ValidationException::withMessages(['student_name' => __($e.'student_contact')]);
        }
        $person = str_contains($contact, '@') ? $this->directory->byContact($contact, null) : $this->directory->byContact(null, Phone::normalize($contact, $context->callingCode));
        $id = match (true) {
            $person === null => null,
            in_array('student', $person->roles, true) => $person->id,
            in_array('guardian', $person->roles, true) => $this->directory->childNamed($person->id, $name),
            default => null,
        };

        return $id ?? throw ValidationException::withMessages(['student_name' => __($e.'no_student', ['name' => $name])]);
    }
}
