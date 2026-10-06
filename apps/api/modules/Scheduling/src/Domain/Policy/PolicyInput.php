<?php

namespace Modules\Scheduling\Domain\Policy;

/** The facts a decision rests on; recorded with the decision. */
final readonly class PolicyInput
{
    public function __construct(
        public PolicyEvent $event,
        /** Hours between the request and the session's start; negative once it started. */
        public float $noticeHours,
        public string $sessionType = 'regular',
        /** Student cancellations and reschedules already counted in the current block. */
        public int $quotaUsed = 0,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'event' => $this->event->value,
            'notice_hours' => round($this->noticeHours, 1),
            'session_type' => $this->sessionType,
            'quota_used' => $this->quotaUsed,
        ];
    }
}
