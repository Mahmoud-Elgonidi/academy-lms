<?php

namespace Modules\Scheduling\Actions\Shifts;

use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Domain\Models\ShiftProposal;

/** A message in the proposal's conversation between the teacher and the admins. */
final class CommentOnShiftProposal
{
    public function __construct(private readonly ShiftProposals $proposals) {}

    public function handle(ShiftProposal $proposal, string $authorId, string $role, string $body): void
    {
        if (! in_array($role, ['teacher', 'admin'], true) || trim($body) === '' || mb_strlen($body) > 2000) {
            throw ValidationException::withMessages(['body' => __('scheduling::shifts.errors.comment')]);
        }
        if ($role === 'teacher' && $proposal->teacher_id !== $authorId) {
            throw ValidationException::withMessages(['body' => __('scheduling::shifts.errors.not_now')]);
        }

        $this->proposals->comment($proposal, $authorId, $role, $body);
    }
}
