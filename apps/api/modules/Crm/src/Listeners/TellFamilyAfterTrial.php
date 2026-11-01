<?php

namespace Modules\Crm\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Crm\Actions\SendTrialReport;
use Modules\Crm\Contracts\Events\TrialAssessed;
use Modules\Crm\Contracts\Events\TrialCancelledByAcademy;
use Modules\Crm\Contracts\Events\TrialMissed;
use Modules\Crm\Domain\NextTrialRules;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Queries\TrialReport;
use Modules\Crm\Support\FamilyContact;
use Modules\Crm\Support\NextTrials;
use Modules\Crm\Support\ReportLinks;
use Modules\Crm\Support\TrialFacts;

/**
 * What the family hears after its free trial (docs/architecture/02, section
 * 27, J2; wording in lang/{ar,en}/notifications.php), by WhatsApp, email and
 * the app: its report is ready (crm.trial_report_ready, with the report's
 * link); its offer ends within a day (crm.trial_offer_ending, FollowUpLeads);
 * the learner missed it (crm.trial_missed_family); or the teacher missed it
 * or the academy cancelled it (crm.trial_sorry_family: an apology, and it
 * does not count as their free trial). The last two carry the "book again"
 * link while the family may book it, else the academy will call. Nothing
 * about a missed trial for a learner who already studies the subject.
 */
final class TellFamilyAfterTrial
{
    public function __construct(
        private readonly SendTrialReport $report,
        private readonly FamilyContact $family,
        private readonly NextTrials $next,
        private readonly ReportLinks $links,
        private readonly TrialFacts $facts,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $lesson = ['student' => 'يارا', 'subject' => 'القرآن', 'when' => 'الثلاثاء 13 أكتوبر · 6:00 م'];
        $next = ['next' => 'اختاروا موعدًا آخر يناسبكم من هنا: https://demo.lms.test/ar/trial/again/…'];
        foreach ([
            ['trial_report_ready', 'lessons', ['student' => 'يارا', 'subject' => 'القرآن', 'teacher' => 'أ. منى',
                'link' => 'https://demo.lms.test/ar/trial/report/…', 'offer' => 'ومعه عرض خاص: خصم 10% على أول باقة لمدة محدودة يبيّنها التقرير.']],
            ['trial_offer_ending', 'billing', ['student' => 'يارا', 'percent' => '10', 'until' => 'الخميس 15 أكتوبر · 8:00 م', 'link' => 'https://demo.lms.test/ar/trial/report/…']],
            ['trial_missed_family', 'lessons', $lesson + $next],
            ['trial_sorry_family', 'lessons', $lesson + $next],
        ] as [$name, $category, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "crm.{$name}", category: $category,
                title: "crm::notifications.{$name}.title", body: "crm::notifications.{$name}.body",
                audience: 'crm::notifications.audience.family', channels: ['whatsapp', 'email', 'push'], sample: $sample,
            ));
        }
    }

    /** The first assessment: the family reads its report. */
    public function assessed(TrialAssessed $event): void
    {
        $trial = LeadTrial::query()->with('lead')->find($event->trialId);
        if ($trial !== null) {
            $this->report->handle($trial);
        }
    }

    /** The trial did not take place: the learner missed it, or the teacher did. */
    public function missed(TrialMissed $event): void
    {
        $trial = LeadTrial::query()->with('lead')->find($event->trialId);
        if ($trial !== null && in_array($trial->outcome, ['student_absent', 'teacher_absent'], true)) {
            $this->tell($trial, $trial->outcome === 'student_absent' ? 'crm.trial_missed_family' : 'crm.trial_sorry_family');
        }
    }

    /** The academy cancelled it (a teacher's leave, a holiday, the office): an apology and a new time. */
    public function cancelled(TrialCancelledByAcademy $event): void
    {
        $trial = LeadTrial::query()->with('lead')->find($event->trialId);
        if ($trial !== null) {
            $this->tell($trial, 'crm.trial_sorry_family');
        }
    }

    private function tell(LeadTrial $trial, string $template): void
    {
        $lead = $trial->lead;
        $session = DB::table('sessions as s')
            ->join('subjects as su', fn ($j) => $j->on('su.academy_id', '=', 's.academy_id')->on('su.id', '=', 's.subject_id'))
            ->where('s.id', $trial->session_id)->first(['s.subject_id', 'su.name_ar', 'su.name_en']);
        if ($session === null || ($lead->learner_id !== null && $this->facts->studying($lead->learner_id, (string) $session->subject_id))) {
            return;
        }
        $facts = $this->next->facts($trial, true);
        $next = $facts !== null && NextTrialRules::whyNot($facts) === null
            ? new Translatable('crm::notifications.next.link', ['link' => $this->links->rebook($trial, $this->family->locale($lead))])
            : new Translatable('crm::notifications.next.call');

        $this->family->send($lead, $template, [
            'student' => TrialReport::firstName($lead->learnerName()),
            'subject' => new Localized((string) $session->name_ar, (string) $session->name_en),
            'when' => $trial->starts_at,
            'next' => $next,
        ], ['screen' => 'family_trial', 'id' => $trial->session_id], "trial-after:{$trial->id}");
    }
}
