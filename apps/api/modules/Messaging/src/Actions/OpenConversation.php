<?php

namespace Modules\Messaging\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Modules\Messaging\MessagingRules;
use Modules\Messaging\Models\Conversation;

/**
 * The conversation between a teacher and a student, made the first time one
 * of them (or one of the student's guardians) opens it, while they teach
 * together and the academy has messages on. One that already exists opens
 * for anyone in it, written to or not.
 */
final class OpenConversation
{
    public function __construct(private readonly MessagingRules $rules) {}

    public function handle(string $actorId, string $teacherId, string $studentId): Conversation
    {
        if ($actorId !== $teacherId && $actorId !== $studentId && ! in_array($actorId, $this->rules->guardiansOf($studentId), true)) {
            throw new AuthorizationException;
        }
        $existing = Conversation::query()->where('teacher_id', $teacherId)->where('student_id', $studentId)->first();
        if ($existing !== null) {
            return $existing;
        }
        if (! $this->rules->enabled()) {
            throw ValidationException::withMessages(['conversation' => __('messaging::chat.why_not.off')]);
        }
        if (! $this->rules->isTeacher($teacherId) || ! $this->rules->teaches($teacherId, $studentId)) {
            throw ValidationException::withMessages(['conversation' => __('messaging::chat.errors.not_teaching')]);
        }

        return Conversation::query()->createOrFirst(['teacher_id' => $teacherId, 'student_id' => $studentId]);
    }
}
