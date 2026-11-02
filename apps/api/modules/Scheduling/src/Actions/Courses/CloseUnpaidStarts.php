<?php

namespace Modules\Scheduling\Actions\Courses;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Contracts\BillingApi;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\EnrollmentStatus;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Models\CourseStart;
use Modules\Scheduling\Support\CourseDays;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * A course the family started on its own and never paid closes itself
 * (docs/architecture/02, section 27, J5a): once its pay-by moment (its first
 * invoice's due moment, StartOwnCourse) has passed and rule 16 cancelled its
 * first lesson for not being paid, it ends today by CancelOwnStart's own
 * rules (only while it waits for its first payment and no money may be on
 * its way: nothing paid, no transfer receipt waiting, no payment page open),
 * through EndEnrollment: its other lessons are cancelled with nothing
 * charged, its weekly times freed, its invoice voided. The family hears it
 * once, kindly, with a link to start again (scheduling.own_start_closed; the
 * course_ended message is not sent to them as well); audited. Every hour
 * (scheduling:courses).
 */
final class CloseUnpaidStarts
{
    public function __construct(
        private readonly EnrollmentApi $enrollments,
        private readonly BillingApi $billing,
        private readonly CancelOwnStart $rules,
        private readonly EndEnrollment $end,
        private readonly CourseDays $days,
        private readonly NotificationsApi $notifications,
        private readonly TenantDirectory $directory,
        private readonly TenantContext $tenants,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $notifications->define(new TemplateDefinition(
            key: 'scheduling.own_start_closed', category: 'billing',
            title: 'scheduling::notifications.own_start_closed.title', body: 'scheduling::notifications.own_start_closed.body',
            audience: 'scheduling::notifications.audience.family_accounts', channels: ['whatsapp', 'email', 'push'],
            sample: ['student' => 'جنى', 'subject' => 'التجويد', 'start' => 'https://demo.lms.test/ar/family/start?…'],
        ));
    }

    /** @return int how many courses closed */
    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $closed = 0;
        foreach (CourseStart::query()->whereNull('cancelled_at')->orderBy('created_at')->get() as $start) {
            if (! $this->due($start, $now)) {
                continue;
            }
            try {
                $closed += $this->close($start->enrollment_id, $now);
            } catch (ValidationException) {
                continue;   // something changed meanwhile (paid a moment ago): it stands
            }
        }

        return $closed;
    }

    private function due(CourseStart $start, CarbonImmutable $now): bool
    {
        if ($this->enrollments->find($start->enrollment_id)?->status !== EnrollmentStatus::PendingPayment) {
            return false;
        }
        $invoice = $this->billing->firstInvoice($start->enrollment_id, $start->payer_id);
        if ($invoice === null || $invoice->dueAt === null || $invoice->dueAt->greaterThan($now) || $invoice->paidMinor > 0) {
            return false;
        }
        // Its first lesson, cancelled by rule 16 (the system, for not being paid).
        $first = DB::table('sessions')->where('enrollment_id', $start->enrollment_id)->orderBy('starts_at')->value('id');

        return $first !== null && DB::table('session_events')->where('session_id', $first)->where('kind', SessionEventKind::StatusChanged->value)
            ->whereRaw("data->>'to' = 'cancelled'")->whereRaw("data->>'reason' = 'unpaid'")->exists();
    }

    private function close(string $enrollmentId, CarbonImmutable $now): int
    {
        $done = DB::transaction(function () use ($enrollmentId, $now) {
            $start = CourseStart::query()->where('enrollment_id', $enrollmentId)->lockForUpdate()->first();
            if ($start === null || $start->cancelled_at !== null || $this->rules->whyNot($start) !== null) {
                return null;
            }
            // Marked first, so the course's ending message knows the family hears this one instead.
            $start->forceFill(['cancelled_at' => $now, 'cancel_reason' => 'unpaid'])->save();
            $ended = $this->end->handle($enrollmentId, $this->days->today()->toDateString(), (string) __('scheduling::start.lapsed_reason'), null);
            app(AuditTrail::class)->record('enrollment.own_start_lapsed', 'enrollment', $enrollmentId, array_filter([
                'lessons' => $ended['cancelled'], 'invoice' => $ended['billing']?->invoiceNumber,
            ], fn ($v) => $v !== null));

            return $start;
        });
        if ($done === null) {
            return 0;
        }

        $course = $this->enrollments->find($enrollmentId);
        $subject = DB::table('subjects')->where('id', $course?->subjectId)->first(['name_ar', 'name_en']);
        $locale = DB::table('users')->where('id', $done->payer_id)->value('locale') === 'en' ? 'en' : 'ar';
        $this->notifications->send(new Message('scheduling.own_start_closed', [$done->payer_id], [
            'student' => (string) DB::table('users')->where('id', $done->learner_id)->value('name'),
            'subject' => new Localized((string) ($subject->name_ar ?? ''), (string) ($subject->name_en ?? $subject->name_ar ?? '')),
            'start' => $this->startAgain($locale, $done),
        ], ['screen' => 'start', 'id' => $done->learner_id], "own-start-closed:{$enrollmentId}"));

        return 1;
    }

    /** The portal's start flow, on the academy's host, for the same learner, subject and plan. */
    private function startAgain(string $locale, CourseStart $start): string
    {
        $subjectId = $this->enrollments->find($start->enrollment_id)?->subjectId;

        return rtrim($this->directory->baseUrl($this->tenants->require()), '/').'/'.$locale.'/family/start?'
            .http_build_query(array_filter(['learner' => $start->learner_id, 'subject' => $subjectId, 'plan' => $start->plan_id]));
    }
}
