<?php

namespace Modules\Scheduling\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Day;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\Events\GroupStarted;
use Modules\Scheduling\Domain\Models\LessonGroup;
use Modules\Scheduling\Domain\Models\Session;

/**
 * A group has its students and starts (docs/architecture/02, "الحصص
 * الجماعية"): each member's family hears it with their own child's name
 * only, and the teacher hears how many it has, with the first lesson.
 */
final class TellGroupStarted
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly PeopleApi $people,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $a = 'scheduling::notifications.audience.';
        $n = 'scheduling::group_outcomes.notifications.';
        $notifications->define(new TemplateDefinition(
            key: 'scheduling.group_started_family', category: 'lessons',
            title: $n.'group_started_family.title', body: $n.'group_started_family.body',
            audience: $a.'family', channels: ['push', 'whatsapp', 'email'],
            sample: ['student' => 'زياد', 'teacher' => 'أ. خالد', 'subject' => 'القرآن', 'when' => 'الثلاثاء 6 أكتوبر · 6:00 م'],
        ));
        $notifications->define(new TemplateDefinition(
            key: 'scheduling.group_started_teacher', category: 'staff',
            title: $n.'group_started_teacher.title', body: $n.'group_started_teacher.body',
            audience: $a.'teacher', channels: ['push', 'whatsapp'],
            sample: ['subject' => 'القرآن', 'count' => '3', 'when' => 'الثلاثاء 6 أكتوبر · 6:00 م'],
        ));
    }

    public function handle(GroupStarted $event): void
    {
        $group = LessonGroup::query()->find($event->groupId);
        if ($group === null || $event->studentIds === []) {
            return;
        }
        $first = Session::query()->where('group_id', $group->id)->where('status', 'scheduled')->orderBy('starts_at')->value('starts_at');
        $when = $first !== null ? CarbonImmutable::parse($first) : new Day($event->startedOn);
        $subject = DB::table('subjects')->where('id', $group->subject_id)->first(['name_ar', 'name_en']);
        $subject = new Localized((string) $subject?->name_ar, (string) $subject?->name_en);
        $teacher = (string) DB::table('users')->where('id', $group->teacher_id)->value('name');
        $names = DB::table('users')->whereIn('id', $event->studentIds)->pluck('name', 'id');
        $action = ['screen' => 'session', 'id' => null];
        $key = "group-started:{$group->id}";

        foreach ($event->studentIds as $studentId) {
            $family = array_values(array_filter([$studentId, ...$this->people->guardiansOf($studentId)], fn ($id) => $id !== $event->actorId));
            $this->notifications->send(new Message('scheduling.group_started_family', $family,
                ['student' => (string) ($names[$studentId] ?? ''), 'teacher' => $teacher, 'subject' => $subject, 'when' => $when], $action, $key));
        }
        $this->notifications->send(new Message('scheduling.group_started_teacher', array_values(array_filter([$group->teacher_id], fn ($id) => $id !== $event->actorId)),
            ['subject' => $subject, 'count' => (string) count($event->studentIds), 'when' => $when], $action, $key));
    }
}
