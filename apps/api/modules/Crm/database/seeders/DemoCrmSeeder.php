<?php

namespace Modules\Crm\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Contracts\BillingApi;
use Modules\Crm\Actions\AssessTrial;
use Modules\Crm\Actions\CreateLead;
use Modules\Crm\Actions\LogLeadContact;
use Modules\Crm\Actions\MarkLeadLost;
use Modules\Crm\Actions\PrepareLearner;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;
use Modules\Crm\Models\LeadTrial;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\ConfirmBooking;
use Modules\Scheduling\Actions\EnrollStudent;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\ScheduleSession;
use Modules\Scheduling\Contracts\DTOs\EnrollmentTerms;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Contracts\DTOs\TeacherSearch;
use Modules\Scheduling\Contracts\Events\TrialLessonBooked;
use Modules\Scheduling\Domain\Availability\StudentPreference;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Queries\FindTeachersQuery;

/**
 * Leads in the demo, one or more at every stage, driven the way the screens
 * drive them: two new ones (one nobody owns yet), one contacted, a trial
 * booked for the coming days, a trial yesterday waiting for Nour's
 * assessment, one assessed, one that subscribed after its trial (a real
 * enrolment, invoice and all), and one lost. Reem (sales@demo.test) owns
 * most of them.
 */
final class DemoCrmSeeder extends Seeder
{
    public function run(CreateLead $create, LogLeadContact $log, PrepareLearner $learner, MarkLeadLost $lost, AssessTrial $assess,
        ScheduleSession $schedule, RecordParticipantJoin $join, ApplyAttendanceDeadlines $deadlines, PlaceBookingHold $hold,
        ConfirmBooking $confirm, EnrollStudent $enroll, FindTeachersQuery $find, BillingApi $billing): void
    {
        $id = fn (string $email) => DB::table('users')->where('email', $email)->value('id');
        [$sales, $owner, $nour, $sara] = [$id('sales@demo.test'), $id('owner@demo.test'), $id('nour@demo.test'), $id('sara@demo.test')];
        if (! $sales || ! $nour || ! $sara) {
            return;
        }
        $subject = fn (string $en) => DB::table('subjects')->where('name_en', $en)->value('id');
        [$quran, $tajweed, $arabic] = [$subject('Quran memorisation'), $subject('Tajweed'), $subject('Arabic')];
        $now = CarbonImmutable::now()->startOfMinute();
        $ago = function (Lead $lead, int $hours) {
            $at = CarbonImmutable::now()->subHours($hours);
            $lead->forceFill(['created_at' => $at, 'stage_changed_at' => $at, 'last_contact_at' => $lead->last_contact_at ? $at : null])->save();
            LeadActivity::query()->where('lead_id', $lead->id)->update(['created_at' => $at]);
        };
        $lead = fn (array $data, ?string $by = null) => $create->handle($data + ['source' => 'whatsapp', 'locale' => 'ar'], $by ?? $sales);

        // New: this morning from an Instagram ad, and one from the website nobody has picked up.
        $ago($lead(['contact_name' => 'منى السيد', 'phone_e164' => '+966551230001', 'country' => 'SA', 'timezone' => 'Asia/Riyadh', 'for_child' => true,
            'learner_name' => 'عمر', 'learner_birth_year' => 2018, 'subject_id' => $quran, 'preferred_days' => [6, 7, 1], 'preferred_from' => 16, 'preferred_to' => 20,
            'source' => 'instagram', 'utm_source' => 'instagram', 'utm_medium' => 'paid', 'utm_campaign' => 'hifz-autumn', 'notes' => 'تريد معلّمة، وعمر يحفظ جزء عمّ.']), 3);
        $daniel = $lead(['contact_name' => 'Daniel Brooks', 'phone_e164' => '+447700900321', 'country' => 'GB', 'timezone' => 'Europe/London', 'locale' => 'en',
            'subject_id' => $arabic, 'preferred_days' => [6], 'preferred_from' => 10, 'preferred_to' => 13, 'source' => 'website', 'notes' => 'Beginner, wants conversational Arabic.']);
        $daniel->forceFill(['owner_id' => null])->save();
        $ago($daniel, 1);

        // Contacted: a WhatsApp and a call.
        $karim = $lead(['contact_name' => 'أبو كريم', 'phone_e164' => '+96551230003', 'country' => 'KW', 'timezone' => 'Asia/Kuwait', 'for_child' => true,
            'learner_name' => 'كريم', 'learner_birth_year' => 2015, 'subject_id' => $tajweed, 'preferred_days' => [7, 2, 4], 'preferred_from' => 17, 'preferred_to' => 21]);
        $log->handle($karim, 'whatsapp', 'أرسلنا الباقات والأسعار ومواعيد المعلّمين.', $sales);
        $log->handle($karim, 'call', 'يريد أن يجرّب هذا الأسبوع بعد المغرب، ويفضّل معلّمًا.', $sales);
        $ago($karim, 26);

        // A trial booked for the coming days, the way Find a teacher books it.
        $saraLead = $lead(['contact_name' => 'أم سارة', 'phone_e164' => '+971551230004', 'country' => 'AE', 'timezone' => 'Asia/Dubai', 'for_child' => true,
            'learner_name' => 'سارة', 'learner_birth_year' => 2016, 'subject_id' => $quran, 'preferred_days' => [2, 3, 4, 5, 6, 7, 1], 'preferred_from' => 18, 'preferred_to' => 22,
            'source' => 'referral', 'notes' => 'جاءت عن طريق أم حمزة.']);
        $log->handle($saraLead, 'call', 'اتفقنا على حصة تجريبية قريبًا.', $sales);
        $this->bookAhead($saraLead, $learner->handle($saraLead, $sales), $find, $hold, $confirm, $sales, $now);

        // Trials that took place: yesterday with Nour (waiting for her assessment), three days ago with Sara (assessed).
        $yahya = $lead(['contact_name' => 'أم يحيى', 'phone_e164' => '+97455123005', 'country' => 'QA', 'timezone' => 'Asia/Qatar', 'for_child' => true,
            'learner_name' => 'يحيى', 'learner_birth_year' => 2017, 'subject_id' => $quran, 'preferred_days' => [1, 3], 'preferred_from' => 17, 'preferred_to' => 20, 'source' => 'facebook']);
        $jana = $lead(['contact_name' => 'أم جنى', 'phone_e164' => '+201001230106', 'country' => 'EG', 'timezone' => 'Africa/Cairo', 'for_child' => true,
            'learner_name' => 'جنى', 'learner_birth_year' => 2013, 'subject_id' => $tajweed, 'preferred_days' => [6, 1], 'preferred_from' => 18, 'preferred_to' => 21, 'source' => 'google']);
        $tamim = $lead(['contact_name' => 'أبو تميم', 'phone_e164' => '+966551230007', 'country' => 'SA', 'timezone' => 'Asia/Riyadh', 'for_child' => true,
            'learner_name' => 'تميم', 'learner_birth_year' => 2016, 'subject_id' => $quran, 'preferred_days' => [7, 3], 'preferred_from' => 17, 'preferred_to' => 21, 'source' => 'tiktok']);
        $past = [];
        foreach ([[$yahya, $nour, $quran, 1], [$jana, $sara, $tajweed, 3], [$tamim, $sara, $quran, 5]] as [$l, $teacher, $subj, $days]) {
            $log->handle($l, 'whatsapp', 'اتفقنا على موعد التجربة.', $sales);
            $past[$l->id] = $this->bookPast($l, $learner->handle($l, $sales), $teacher, $subj, $now->subDays($days), $schedule, $join, $sales);
        }
        $deadlines->handle($now);

        $plan = collect($billing->planOptions(30))->first(fn ($p) => $p->sessionsPerWeek === 2);
        foreach ([[$jana, 'تقرأ جيدًا، وتحتاج إلى العمل على أحكام النون الساكنة والمدود.', 'تركيزها عالٍ. حصتان في الأسبوع ستجعلانها تنهي المستوى الأول في شهرين.'],
            [$tamim, 'يحفظ جزء عمّ، ومخارج الحروف تحتاج إلى ضبط.', 'متحمّس جدًا. الأفضل أن نبدأ بحصتين ونراجع الحفظ في كل حصة.']] as [$l, $level, $notes]) {
            if (($trial = LeadTrial::query()->where('lead_id', $l->id)->where('status', 'attended')->first()) !== null) {
                $assess->handle($trial, ['level' => $level, 'plan_id' => $plan?->id, 'per_week' => 2, 'notes' => $notes], $trial->teacher_id);
            }
        }

        // Tamim subscribed with Sara after his trial: a real enrolment from Find a teacher.
        if ($plan !== null && ($region = collect($billing->regions())->first(fn ($r) => $r->isDefault)) !== null) {
            $this->enrolAfterTrial($tamim->refresh(), $sara, $plan->id, $region->id, $find, $hold, $enroll, $owner ?? $sales, $now);
        }

        // Lost: the price, for now.
        $malek = $lead(['contact_name' => 'أبو مالك', 'phone_e164' => '+962791230008', 'country' => 'JO', 'timezone' => 'Asia/Amman', 'for_child' => true,
            'learner_name' => 'مالك', 'learner_birth_year' => 2014, 'subject_id' => $quran, 'source' => 'facebook']);
        $log->handle($malek, 'call', 'يرى أن الباقة غالية، وسيعود بعد الامتحانات.', $sales);
        $lost->handle($malek, 'price', 'نتواصل معه مرة أخرى آخر الشهر.', $sales);
        $ago($malek, 120);
    }

    /** The first free time that suits them in the next week, with a teacher who gives trials. */
    private function bookAhead(Lead $lead, string $learnerId, FindTeachersQuery $find, PlaceBookingHold $hold, ConfirmBooking $confirm, string $by, CarbonImmutable $now): void
    {
        $windows = array_map(fn (int $d) => ['weekday' => $d, 'start' => $lead->preferred_from * 60, 'end' => $lead->preferred_to * 60], $lead->preferred_days);
        $pref = new StudentPreference($lead->timezone, $windows, 1, 30, $now->addDay()->setTimezone($lead->timezone)->startOfDay(), 2, 1);
        foreach ($find->run(new TeacherSearch((string) $lead->subject_id, $pref, forTrial: true, searcherId: $by)) as $result) {
            foreach ($result['candidates'] as $candidate) {
                try {
                    $batch = $hold->handle($result['teacher']->id, $learnerId, [min($candidate->occurrences)], 30, $by);
                    $confirm->trial($batch, $learnerId, (string) $lead->subject_id, $by);

                    return;
                } catch (ValidationException) {
                    continue;
                }
            }
        }
    }

    /** A trial in the past, attended by both, closed by the deadlines like any lesson. */
    private function bookPast(Lead $lead, string $learnerId, string $teacherId, string $subjectId, CarbonImmutable $day, ScheduleSession $schedule, RecordParticipantJoin $join, string $by): ?Session
    {
        foreach (['16:00', '20:30', '21:30', '14:00', '22:30'] as $time) {
            $at = CarbonImmutable::parse($day->setTimezone('Africa/Cairo')->toDateString().' '.$time, 'Africa/Cairo')->utc();
            try {
                $session = $schedule->handle(new NewSession($teacherId, $learnerId, $subjectId, $at, 30, 'Africa/Cairo', 'trial', 'free'), $by);
            } catch (ValidationException) {
                continue;
            }
            event(new TrialLessonBooked($session->academy_id, $session->id, $learnerId, $teacherId, $subjectId, $at->toIso8601ZuluString(), $by));
            $join->handle($session->refresh(), $teacherId, $at->subMinute());
            $join->handle($session->refresh(), $learnerId, $at->addMinute());

            return $session;
        }

        return null;
    }

    /** Weekly lessons with the trial's teacher, twice a week from next week, enrolled the way Find a teacher enrols. */
    private function enrolAfterTrial(Lead $lead, string $teacherId, string $planId, string $regionId, FindTeachersQuery $find, PlaceBookingHold $hold, EnrollStudent $enroll, string $by, CarbonImmutable $now): void
    {
        $windows = array_map(fn (int $d) => ['weekday' => $d, 'start' => 15 * 60, 'end' => 23 * 60], [6, 7, 1, 2, 3, 4]);
        $pref = new StudentPreference($lead->timezone, $windows, 2, 30, $now->addWeek()->setTimezone($lead->timezone)->startOfDay(), 8, 8);
        $result = collect($find->run(new TeacherSearch((string) $lead->subject_id, $pref, searcherId: $by)))->first(fn ($r) => $r['teacher']->id === $teacherId);
        if ($result === null || $result['pattern'] === null) {
            return;
        }
        try {
            $batch = $hold->handle($teacherId, $lead->learner_id, array_merge(...array_map(fn ($c) => $c->occurrences, $result['pattern'])), 30, $by);
            $enroll->handle($batch, (string) $lead->learner_id, (string) $lead->subject_id, new EnrollmentTerms($planId, (string) $lead->contact_id, $regionId), $by);
        } catch (ValidationException) {
            // The demo carries on without it.
        }
    }
}
