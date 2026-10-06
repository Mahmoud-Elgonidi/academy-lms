<?php

namespace Modules\Scheduling\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Models\Session;

/**
 * The family turns down a substitute (docs/architecture/01, section 8): the
 * lesson is cancelled as the academy's doing (rule 21), so its minutes come
 * back as makeup credit, until it starts.
 */
final class RefuseSubstitute
{
    public function __construct(private readonly CancelSession $cancel) {}

    public function handle(Session $session, ?string $note, string $actorId): Session
    {
        if ($session->original_teacher_id === null || $session->status !== SessionStatus::Scheduled || $session->starts_at->isPast()) {
            throw ValidationException::withMessages(['session' => __('scheduling::timeoff.errors.no_substitute')]);
        }

        return $this->cancel->handle($session, 'family', 'substitute_refused', $note, $actorId);
    }
}
