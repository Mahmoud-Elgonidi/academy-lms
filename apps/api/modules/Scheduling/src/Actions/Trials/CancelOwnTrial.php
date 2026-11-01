<?php

namespace Modules\Scheduling\Actions\Trials;

use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Domain\Models\Session;

/**
 * "We cannot come" from the family's own link to a coming free trial: the
 * family's cancellation (CancelSession, asked by the student, as on their
 * lessons screen; a trial costs nothing). The family and the teacher hear
 * of it, and the CRM tells whoever follows the lead.
 */
final class CancelOwnTrial
{
    public function __construct(private readonly CancelSession $cancel) {}

    public function handle(Session $session): Session
    {
        return $this->cancel->handle($session, 'student', 'other', null, null);
    }
}
