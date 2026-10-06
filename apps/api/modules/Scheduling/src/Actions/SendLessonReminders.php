<?php

namespace Modules\Scheduling\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Scheduling\Listeners\SendSchedulingNotifications;
use Modules\Scheduling\Queries\SessionPeople;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Part of the every-minute sweep (docs/architecture/01-features.md,
 * section 15, and flow "يوم الحصة"):
 *
 * - a day before, and an hour before, the family is reminded (and the teacher, an hour before);
 * - 3 minutes in, a student who has not joined gets "join now" (and the guardians), even in quiet hours;
 * - 2 minutes in, a teacher who has not joined is nudged, and the supervisors told.
 *
 * Each message goes once per session (dedupe keys), so overlapping runs are harmless.
 * Unpaid sessions get no day-before reminder: they are cancelled at that point (rule 16).
 */
final class SendLessonReminders
{
    public const JOIN_NOW_AFTER_MIN = 3;

    public const TEACHER_LATE_AFTER_MIN = 2;

    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly SessionPeople $sessions,
        private readonly IdentityApi $identity,
    ) {}

    public function handle(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $sent = 0;

        foreach ($this->starting($now->addHours(23), $now->addHours(24), ['funded', 'free', 'makeup']) as $id) {
            $sent += $this->toFamily($id, 'scheduling.reminder_day', "reminder-day:{$id}");
        }
        foreach ($this->starting($now, $now->addHour()) as $id) {
            $sent += $this->toFamily($id, 'scheduling.reminder_hour', "reminder-hour:{$id}");
            $sent += $this->toTeacher($id, 'scheduling.reminder_hour_teacher', "reminder-hour:{$id}");
        }
        foreach ($this->notJoined('student', $now, self::JOIN_NOW_AFTER_MIN) as $id) {
            $sent += $this->toFamily($id, 'scheduling.join_now', "join-now:{$id}", urgent: true);
        }
        foreach ($this->notJoined('teacher', $now, self::TEACHER_LATE_AFTER_MIN) as $id) {
            $sent += $this->toTeacher($id, 'scheduling.teacher_join_now', "teacher-late:{$id}", urgent: true);
            $s = $this->sessions->of($id);
            if ($s !== null) {
                $supervisors = array_values(array_diff($this->identity->usersWithAbility(Ability::ViewLiveBoard), [$s['teacher_id']]));
                $sent += $this->notifications->send(new Message('scheduling.teacher_late', $supervisors, SendSchedulingNotifications::lessonData($s),
                    ['screen' => 'live_board', 'id' => $id], "teacher-late:{$id}"));
            }
        }

        return $sent;
    }

    /**
     * @param  list<string>|null  $funding
     * @return list<string> scheduled sessions starting in (from, to]
     */
    private function starting(CarbonImmutable $from, CarbonImmutable $to, ?array $funding = null): array
    {
        return DB::table('sessions')->where('status', 'scheduled')->where('starts_at', '>', $from)->where('starts_at', '<=', $to)
            ->when($funding !== null, fn ($q) => $q->whereIn('funding_status', $funding))
            ->pluck('id')->all();
    }

    /** @return list<string> sessions under way for at least $minutes (up to 15) whose $role has not joined */
    private function notJoined(string $role, CarbonImmutable $now, int $minutes): array
    {
        return DB::table('sessions as s')
            ->join('session_participants as p', fn ($j) => $j->on('p.academy_id', '=', 's.academy_id')->on('p.session_id', '=', 's.id')
                ->where('p.role', $role)->where('p.is_active', true))
            ->whereIn('s.status', ['scheduled', 'in_progress'])
            ->where('s.starts_at', '<=', $now->subMinutes($minutes))->where('s.starts_at', '>', $now->subMinutes(15))
            ->whereNull('p.first_join_at')
            ->pluck('s.id')->all();
    }

    private function toFamily(string $sessionId, string $template, string $key, bool $urgent = false): int
    {
        $s = $this->sessions->of($sessionId);

        return $s === null ? 0 : $this->notifications->send(new Message($template, $s['family'], SendSchedulingNotifications::lessonData($s),
            ['screen' => 'session', 'id' => $sessionId], $key, $urgent));
    }

    private function toTeacher(string $sessionId, string $template, string $key, bool $urgent = false): int
    {
        $s = $this->sessions->of($sessionId);

        return $s === null ? 0 : $this->notifications->send(new Message($template, [$s['teacher_id']], SendSchedulingNotifications::lessonData($s),
            ['screen' => 'session', 'id' => $sessionId], $key, $urgent));
    }
}
