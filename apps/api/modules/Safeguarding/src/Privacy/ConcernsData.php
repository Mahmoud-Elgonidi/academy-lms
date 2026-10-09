<?php

namespace Modules\Safeguarding\Privacy;

use Modules\Safeguarding\Enums\ConcernStatus;
use Modules\Safeguarding\Models\Concern;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * The concerns a person raised, as they wrote them, with where each stands
 * and the reply they were given (never the leads' notes, the outcome, or a
 * concern raised by someone else about them: those belong to the inquiry).
 * Nothing is erased: a safeguarding record is kept, and the person's name
 * goes with their account. An erasure waits while a concern they raised, or
 * one about them, is still open.
 */
final class ConcernsData implements PersonalDataSource
{
    public function key(): string
    {
        return 'safeguarding';
    }

    public function export(string $userId): PersonalDataPart
    {
        return new PersonalDataPart([
            'concerns_raised' => Concern::query()->where('reporter_id', $userId)->orderBy('created_at')->get()
                ->map(fn (Concern $c) => [
                    'number' => $c->reference(), 'kind' => $c->category->value, 'urgent' => $c->urgent, 'text' => $c->body,
                    'status' => $c->status->value, 'reply' => $c->reporter_reply, 'at' => $c->created_at?->toIso8601ZuluString(),
                ])->all(),
        ]);
    }

    public function blockers(string $userId): array
    {
        $open = Concern::query()->where('status', '<>', ConcernStatus::Closed->value)
            ->where(fn ($q) => $q->where('reporter_id', $userId)->orWhere('student_id', $userId)->orWhere('teacher_id', $userId))->exists();

        return $open ? [__('safeguarding::concerns.privacy.open')] : [];
    }

    public function erase(string $userId): array
    {
        return [];
    }
}
