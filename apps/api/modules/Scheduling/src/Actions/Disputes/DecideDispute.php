<?php

namespace Modules\Scheduling\Actions\Disputes;

use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\ResolveReview;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\SessionDispute;

/**
 * The academy decides a disputed lesson (docs/architecture/01, section 8):
 * what the lesson was after all, and an answer whoever disputed reads. The
 * decision is the lesson's review (ResolveReview): credit and pay follow
 * the outcome, and the dispute closes upheld when it changed, rejected when
 * it stayed.
 */
final class DecideDispute
{
    public function __construct(private readonly ResolveReview $resolve) {}

    public function handle(SessionDispute $dispute, string $outcome, string $answer, string $actorId): SessionDispute
    {
        $e = 'scheduling::disputes.errors.';
        if (! $dispute->isOpen()) {
            throw ValidationException::withMessages(['dispute' => __($e.'decided')]);
        }
        $answer = trim($answer);
        if ($answer === '' || mb_strlen($answer) > DisputeLesson::NOTE_MAX) {
            throw ValidationException::withMessages(['answer' => __($e.'answer')]);
        }

        $session = Session::query()->findOrFail($dispute->session_id);
        // A group lesson's dispute is about one student's seat: the decision is theirs alone.
        $this->resolve->handle($session, $outcome, $answer, $actorId, $session->isGroup() ? $dispute->student_id : null);

        return $dispute->refresh();
    }
}
