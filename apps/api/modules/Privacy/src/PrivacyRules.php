<?php

namespace Modules\Privacy;

use Illuminate\Support\Facades\Gate;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Who may ask about whose data. In the family's area: an adult (the classic
 * look: guardians and adult students) for themselves and for the learners
 * they follow; a younger student's requests are their guardian's. A
 * teacher for themselves. Someone at the academy who handles these
 * requests, for anyone, writing it down for them.
 */
final class PrivacyRules
{
    public const SIDES = ['family', 'teacher', 'staff'];

    public function __construct(private readonly FamilyView $family) {}

    public function canAsk(string $viewerId, string $subjectId, string $side): bool
    {
        return match ($side) {
            'family' => $this->family->stage($viewerId) === LearnerStage::Classic
                && ($subjectId === $viewerId || $this->family->follows($viewerId, $subjectId)),
            'teacher' => $subjectId === $viewerId,
            'staff' => Gate::allows(Ability::HandleDataRequests->value),
            default => false,
        };
    }

    /** Where the signed-in person asks from on their own: a teacher's area, or the family's. */
    public function ownSide(): string
    {
        return Gate::allows(Ability::ViewOwnPerformance->value) ? 'teacher' : 'family';
    }

    /** @return list<array{id: string, name: string, self: bool}> whom a person may ask about from their own area */
    public function subjectsFor(string $viewerId, string $viewerName, string $side): array
    {
        return $side === 'teacher' ? [['id' => $viewerId, 'name' => $viewerName, 'self' => true]] : $this->familySubjects($viewerId, $viewerName);
    }

    /** @return list<array{id: string, name: string, self: bool}> whom a family member may ask about: themselves first, then the learners they follow */
    public function familySubjects(string $viewerId, string $viewerName): array
    {
        if ($this->family->stage($viewerId) !== LearnerStage::Classic) {
            return [];
        }
        $out = [['id' => $viewerId, 'name' => $viewerName, 'self' => true]];
        foreach ($this->family->learners($viewerId) as $learner) {
            if ($learner->id !== $viewerId) {
                $out[] = ['id' => $learner->id, 'name' => $learner->name, 'self' => false];
            }
        }

        return $out;
    }
}
