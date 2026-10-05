<?php

namespace Modules\Scheduling\Domain\LiveBoard;

use Carbon\CarbonImmutable;
use Modules\Scheduling\Domain\Enums\FundingStatus;
use Modules\Scheduling\Domain\Enums\SessionEventKind;
use Modules\Scheduling\Domain\Enums\SessionStatus;
use Modules\Scheduling\Domain\Enums\SessionType;

/** A session as the live board sees it: one read, no models. */
final readonly class BoardSession
{
    /**
     * @param  list<BoardPerson>  $guardians
     * @param  list<BoardEvent>  $events  oldest first
     */
    public function __construct(
        public string $id,
        public SessionType $type,
        public SessionStatus $status,
        public FundingStatus $funding,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public string $subjectAr,
        public string $subjectEn,
        public BoardPerson $teacher,
        public ?BoardPerson $student,
        public array $guardians,
        public array $events,
        public ?string $reviewReason = null,
    ) {}

    public function durationMin(): int
    {
        return (int) $this->startsAt->diffInMinutes($this->endsAt);
    }

    public function subject(): string
    {
        return app()->getLocale() === 'ar' ? $this->subjectAr : $this->subjectEn;
    }

    /** @return list<BoardEvent> */
    public function eventsOf(SessionEventKind ...$kinds): array
    {
        return array_values(array_filter($this->events, fn (BoardEvent $e) => in_array($e->kind, $kinds, true)));
    }

    public function lastHandling(CarbonImmutable $since): ?BoardEvent
    {
        $handled = array_filter(
            $this->eventsOf(SessionEventKind::ContactLogged, SessionEventKind::DecisionLogged),
            fn (BoardEvent $e) => $e->at->greaterThanOrEqualTo($since),
        );

        return $handled === [] ? null : end($handled);
    }

    public function hasDecision(string $decision): bool
    {
        foreach ($this->eventsOf(SessionEventKind::DecisionLogged) as $event) {
            if (($event->data['decision'] ?? null) === $decision) {
                return true;
            }
        }

        return false;
    }
}
