<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionEvent;

/**
 * A coordinator contacted the teacher, student or guardian about a session
 * and records the outcome, so the next person sees it is being handled.
 */
final class LogContactAttempt
{
    public const TARGETS = ['teacher', 'student', 'guardian'];

    public const OUTCOMES = ['joining_now', 'running_late', 'not_coming', 'no_answer'];

    public function handle(Session $session, string $target, string $outcome, ?string $note, string $actorId): SessionEvent
    {
        if (! in_array($target, self::TARGETS, true) || ! in_array($outcome, self::OUTCOMES, true)) {
            throw ValidationException::withMessages(['outcome' => __('scheduling::board.errors.bad_contact')]);
        }

        return SessionEvent::record($session, SessionEventKind::ContactLogged, array_filter([
            'target' => $target,
            'outcome' => $outcome,
            'note' => $note !== null ? mb_substr(trim($note), 0, 500) : null,
        ], fn ($v) => $v !== null && $v !== ''), $actorId);
    }
}
