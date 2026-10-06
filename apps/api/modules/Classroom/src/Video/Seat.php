<?php

namespace Modules\Classroom\Video;

/** Who enters the room and as what: the teacher, the student (or a guardian at their device), or a hidden supervisor. */
final readonly class Seat
{
    public const ROLES = ['teacher', 'student', 'observer'];

    public function __construct(
        public string $identity,     // the user id; "observer:{user id}" for a supervisor
        public string $name,
        public string $role,
        public string $userId,
    ) {}

    public function publishes(): bool
    {
        return $this->role !== 'observer';
    }

    public function hidden(): bool
    {
        return $this->role === 'observer';
    }
}
