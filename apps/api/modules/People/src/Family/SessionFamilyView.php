<?php

namespace Modules\People\Family;

use Illuminate\Support\Facades\DB;
use Modules\People\Contracts\DTOs\Learner;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Models\GuardianStudent;

/** FamilyView with the choice kept in the browser session. */
final class SessionFamilyView implements FamilyView
{
    private const SESSION_KEY = 'family.learner';

    /** @var array<string, list<Learner>> */
    private array $cache = [];

    /** @var array<string, LearnerStage> */
    private array $stages = [];

    public function learners(string $viewerId): array
    {
        if (isset($this->cache[$viewerId])) {
            return $this->cache[$viewerId];
        }

        $isStudent = DB::table('user_roles')->where('user_id', $viewerId)->where('role', 'student')->exists();
        $children = GuardianStudent::query()->where('guardian_id', $viewerId)->orderByDesc('is_primary')->pluck('student_id')->all();
        $ids = array_values(array_unique([...($isStudent ? [$viewerId] : []), ...$children]));
        $names = DB::table('users')->whereIn('id', $ids)->where('status', 'active')->pluck('name', 'id');

        return $this->cache[$viewerId] = array_values(array_filter(array_map(
            fn (string $id) => isset($names[$id]) ? new Learner($id, $names[$id], $id === $viewerId) : null,
            $ids,
        )));
    }

    public function current(string $viewerId): ?Learner
    {
        $learners = $this->learners($viewerId);
        $chosen = session(self::SESSION_KEY);

        foreach ($learners as $learner) {
            if ($learner->id === $chosen) {
                return $learner;
            }
        }

        return $learners[0] ?? null;
    }

    public function learner(string $viewerId, ?string $learnerId): ?Learner
    {
        $learners = $this->learners($viewerId);
        if ($learnerId === null || $learnerId === '') {
            return $learners[0] ?? null;
        }
        foreach ($learners as $learner) {
            if ($learner->id === $learnerId) {
                return $learner;
            }
        }

        return null;
    }

    public function choose(string $viewerId, string $learnerId): void
    {
        if ($this->follows($viewerId, $learnerId)) {
            session([self::SESSION_KEY => $learnerId]);
        }
    }

    public function stage(string $viewerId): LearnerStage
    {
        $self = array_filter($this->learners($viewerId), fn (Learner $l) => $l->isSelf);

        return $this->stages[$viewerId] ??= $self === [] ? LearnerStage::Classic : app(PeopleApi::class)->studentProfile($viewerId)->stage;
    }

    public function follows(string $viewerId, string $learnerId): bool
    {
        foreach ($this->learners($viewerId) as $learner) {
            if ($learner->id === $learnerId) {
                return true;
            }
        }

        return false;
    }
}
