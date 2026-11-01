<?php

namespace Modules\Scheduling\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\DTOs\WeeklyTimes;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Enrollment\Contracts\Events\EnrollmentActivated;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Queries\Start\CourseTimes;
use Modules\Scheduling\Queries\Start\StartRules;

/**
 * Welcome to the course (docs/architecture/02, section 27, J3): once its first
 * payment is in (or it joined a paid shared package), whether the family
 * started it or the office did, the family's accounts (its guardians, and an
 * adult learner; never a child's own) hear it on WhatsApp, email, the app and
 * the inbox: the teacher's name as families read it, the weekly times on each
 * reader's own clock, the first lesson with its own link to the class page
 * (or that a forming group starts when it is full), and where to get the app
 * (the platform's app links, when set). Whoever has no way to sign in yet (an
 * account the CRM or the office made) also gets People's welcome with their
 * link to choose a password. A course brought over by an import was under way
 * before: no welcome. Its key is enrollment.course_started: Scheduling sends
 * it because only it knows the weekly times.
 */
final class WelcomeToCourse
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly EnrollmentApi $enrollments,
        private readonly PeopleApi $people,
        private readonly CourseTimes $times,
        private readonly StartRules $rules,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $notifications->define(new TemplateDefinition(
            key: 'enrollment.course_started', category: 'lessons',
            title: 'scheduling::notifications.course_started.title', body: 'scheduling::notifications.course_started.body',
            audience: 'scheduling::notifications.audience.family_accounts', channels: ['whatsapp', 'email', 'push'],
            sample: ['student' => 'جنى', 'subject' => 'التجويد', 'teacher' => 'أ. سارة', 'times' => 'السبت 6:00 م والاثنين 6:00 م',
                'first' => 'السبت 7 نوفمبر · 6:00 م', 'join' => 'ادخلوها قبل موعدها بدقائق من التطبيق، أو من هذا الرابط:', 'link' => 'https://demo.lms.test/class/…', 'app' => 'حمّلوا التطبيق: https://…'],
        ));
    }

    public function handle(EnrollmentActivated $event): void
    {
        if ($event->reason === 'imported') {
            return;
        }
        $course = $this->enrollments->find($event->enrollmentId);
        if ($course === null) {
            return;
        }
        $recipients = $this->people->guardiansOf($course->studentId);
        if (! $this->people->isMinor($course->studentId)) {
            $recipients[] = $course->studentId;
        }
        $recipients = array_values(array_unique($recipients));
        if ($recipients === []) {
            return;
        }

        $subject = DB::table('subjects')->where('id', $course->subjectId)->first(['name_ar', 'name_en']);
        $first = $this->times->firstLesson($course->id);
        $android = (string) config('platform.app.android_url');
        $ios = (string) config('platform.app.ios_url');
        $app = match (true) {
            $android !== '' && $ios !== '' => new Translatable('scheduling::start.welcome.app_both', ['android' => $android, 'ios' => $ios]),
            $android !== '' => new Translatable('scheduling::start.welcome.app_android', ['android' => $android]),
            $ios !== '' => new Translatable('scheduling::start.welcome.app_ios', ['ios' => $ios]),
            default => '',
        };

        $this->notifications->send(new Message('enrollment.course_started', $recipients, [
            'student' => $this->rules->firstName((string) DB::table('users')->where('id', $course->studentId)->value('name')),
            'subject' => new Localized((string) ($subject->name_ar ?? ''), (string) ($subject->name_en ?? '')),
            'teacher' => $this->rules->teacherName($course->teacherId),
            'times' => new WeeklyTimes($this->times->weekly($course->id)),
            'first' => $first === null ? new Translatable('scheduling::start.welcome.first_later') : $first['at'],
            'join' => $first === null ? '' : new Translatable('scheduling::start.welcome.join'),
            'app' => $app,
        ], ['screen' => 'session', 'id' => $first['id'] ?? null], "course-started:{$course->id}"));

        // An account made for the family (the CRM, the office) with no way in yet: its link to choose a password, once.
        $this->people->sendWelcome($recipients);
    }
}
