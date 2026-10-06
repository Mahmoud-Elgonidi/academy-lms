<?php

namespace Modules\Scheduling\Listeners;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Scheduling\Contracts\Events\SessionCancelled;
use Modules\Scheduling\Contracts\Events\SessionRescheduled;
use Modules\Scheduling\Contracts\Events\ShiftProposalApproved;
use Modules\Scheduling\Contracts\Events\ShiftProposalChangesRequested;
use Modules\Scheduling\Contracts\Events\ShiftProposalCountered;
use Modules\Scheduling\Contracts\Events\ShiftProposalRejected;
use Modules\Scheduling\Contracts\Events\ShiftProposalSubmitted;
use Modules\Scheduling\Queries\SessionPeople;
use Modules\SharedKernel\Authorization\Ability;

/**
 * What the family, the teacher and the reviewers hear about lessons and
 * shifts (docs/architecture/01-features.md, section 15). Whoever made the
 * change is not told about it. The wording is in lang/{ar,en}/notifications.php.
 */
final class SendSchedulingNotifications
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly SessionPeople $sessions,
        private readonly IdentityApi $identity,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        $lesson = ['student' => 'زياد', 'teacher' => 'أ. خالد', 'subject' => 'النحو', 'when' => 'الأحد 11 أكتوبر · 18:00'];
        $a = 'scheduling::notifications.audience.';
        foreach ([
            ['reminder_day', 'lessons', $a.'family', ['push', 'email'], $lesson],
            ['reminder_hour', 'lessons', $a.'family', ['push', 'whatsapp'], $lesson],
            ['reminder_hour_teacher', 'staff', $a.'teacher', ['push'], $lesson],
            ['join_now', 'lessons', $a.'family', ['whatsapp', 'push'], $lesson],
            ['teacher_join_now', 'staff', $a.'teacher', ['whatsapp', 'push'], $lesson],
            ['teacher_late', 'staff', $a.'supervisors', ['push'], $lesson],
            ['session_cancelled', 'lessons', $a.'family', ['whatsapp', 'push'], $lesson + ['outcome' => 'وليكم حصة تعويض.']],
            ['session_cancelled_teacher', 'staff', $a.'teacher', ['push', 'whatsapp'], $lesson],
            ['session_moved', 'lessons', $a.'family', ['whatsapp', 'push'], $lesson + ['from' => 'السبت 10 أكتوبر · 18:00']],
            ['session_moved_teacher', 'staff', $a.'teacher', ['push', 'whatsapp'], $lesson + ['from' => 'السبت 10 أكتوبر · 18:00']],
            ['proposal_submitted', 'staff', $a.'reviewers', ['push', 'email'], ['teacher' => 'أ. سارة']],
            ['proposal_answered', 'staff', $a.'teacher', ['push', 'whatsapp'], ['outcome' => 'اتعتمد.']],
        ] as [$name, $category, $audience, $channels, $sample]) {
            $notifications->define(new TemplateDefinition(
                key: "scheduling.{$name}", category: $category,
                title: "scheduling::notifications.{$name}.title", body: "scheduling::notifications.{$name}.body",
                audience: $audience, channels: $channels, sample: $sample,
            ));
        }
    }

    public function cancelled(SessionCancelled $event): void
    {
        $s = $this->sessions->of($event->sessionId);
        if ($s === null) {
            return;
        }
        $credit = DB::table('sessions')->where('id', $event->sessionId)->value('decision');
        $credit = json_decode((string) $credit, true)['credit'] ?? 'none';
        $outcome = $event->reason === 'unpaid' ? 'unpaid' : (in_array($credit, ['makeup', 'refund', 'forfeit', 'consumed'], true) ? $credit : 'none');
        $data = self::lessonData($s) + ['outcome' => new Translatable("scheduling::notifications.outcomes.{$outcome}")];
        $action = ['screen' => 'session', 'id' => $s['session_id']];

        $this->notifications->send(new Message('scheduling.session_cancelled', $this->without($s['family'], $event->actorId), $data, $action, "cancelled:{$s['session_id']}"));
        $this->notifications->send(new Message('scheduling.session_cancelled_teacher', $this->without([$s['teacher_id']], $event->actorId), $data, $action, "cancelled:{$s['session_id']}"));
    }

    public function rescheduled(SessionRescheduled $event): void
    {
        $s = $this->sessions->of($event->sessionId);
        if ($s === null) {
            return;
        }
        $data = self::lessonData($s) + ['from' => CarbonImmutable::parse($event->fromUtc)];
        $action = ['screen' => 'session', 'id' => $s['session_id']];
        $key = "moved:{$s['session_id']}:{$s['starts_at']->getTimestamp()}";

        $this->notifications->send(new Message('scheduling.session_moved', $this->without($s['family'], $event->actorId), $data, $action, $key));
        $this->notifications->send(new Message('scheduling.session_moved_teacher', $this->without([$s['teacher_id']], $event->actorId), $data, $action, $key));
    }

    public function proposalSubmitted(ShiftProposalSubmitted $event): void
    {
        $this->notifications->send(new Message('scheduling.proposal_submitted', $this->without($this->identity->usersWithAbility(Ability::ReviewShifts), $event->actorId), [
            'teacher' => (string) DB::table('users')->where('id', $event->teacherId)->value('name'),
        ], ['screen' => 'shift_proposal', 'id' => $event->proposalId]));
    }

    public function proposalAnswered(ShiftProposalApproved|ShiftProposalRejected|ShiftProposalChangesRequested|ShiftProposalCountered $event): void
    {
        $answer = match (true) {
            $event instanceof ShiftProposalApproved => 'approved',
            $event instanceof ShiftProposalRejected => 'rejected',
            $event instanceof ShiftProposalChangesRequested => 'changes_requested',
            default => 'countered',
        };

        $this->notifications->send(new Message('scheduling.proposal_answered', $this->without([$event->teacherId], $event->actorId), [
            'outcome' => new Translatable("scheduling::notifications.answers.{$answer}"),
        ], ['screen' => 'shift_proposal', 'id' => $event->proposalId]));
    }

    /** @return array<string, mixed> */
    public static function lessonData(array $s): array
    {
        return ['student' => $s['student'], 'teacher' => $s['teacher'], 'subject' => $s['subject'], 'when' => $s['starts_at']];
    }

    /** @param  list<string>  $ids */
    private function without(array $ids, ?string $actorId): array
    {
        return array_values(array_filter($ids, fn ($id) => $id !== $actorId));
    }
}
