<?php

namespace Modules\Scheduling\Support;

use Illuminate\Validation\ValidationException;
use Modules\People\Contracts\PeopleApi;

/**
 * Keeping children safe (docs/architecture/02, section 15): no new lesson
 * with a student under 18 goes to a teacher People does not clear for it (a
 * pause while a concern is looked into, or, when the academy asks for one, no
 * valid criminal record check). Every way a lesson is booked, moved to another
 * teacher or covered asks here; lessons already on the calendar stay for the
 * academy to move.
 */
final class MinorsGuard
{
    public function __construct(private readonly PeopleApi $people) {}

    /** @param  string|list<string>|null  $studentIds */
    public function assert(string $teacherId, string|array|null $studentIds): void
    {
        $studentIds = array_values(array_filter((array) $studentIds));
        if ($studentIds === [] || ! in_array(true, $this->people->minors($studentIds), true)) {
            return;
        }
        $clearance = $this->people->clearances([$teacherId])[$teacherId];
        if (! $clearance->cleared) {
            throw ValidationException::withMessages(['teacher' => __('scheduling::policy.errors.not_cleared', [
                'state' => __('people::checks.clearance.'.$clearance->state),
            ])]);
        }
    }

    /**
     * Of these teachers, the ones who may take a student under 18.
     *
     * @param  list<string>  $teacherIds
     * @return list<string>
     */
    public function cleared(array $teacherIds): array
    {
        return array_values(array_keys(array_filter($this->people->clearances($teacherIds), fn ($c) => $c->cleared)));
    }
}
