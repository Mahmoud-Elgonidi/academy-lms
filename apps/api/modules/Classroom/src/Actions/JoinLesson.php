<?php

namespace Modules\Classroom\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Authorization\ClassroomAccess;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\Room;
use Modules\Classroom\Video\JoinTicket;
use Modules\Classroom\Video\ProviderManager;
use Modules\Classroom\Video\Seat;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Contracts\AttendanceApi;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * A ticket into a lesson's room (docs/architecture/01-features.md, section
 * 9): for its people, from 10 minutes before the lesson until 10 minutes
 * after its end, while it is not cancelled or closed. The room is made on
 * the first ticket, with the academy's provider at that moment.
 */
final class JoinLesson
{
    public function __construct(
        private readonly AttendanceApi $lessons,
        private readonly ClassroomAccess $access,
        private readonly ProviderManager $providers,
        private readonly TenantContext $tenant,
        private readonly PeopleApi $people,
    ) {}

    public function handle(string $sessionId, string $userId, ?CarbonImmutable $now = null): JoinTicket
    {
        $now ??= CarbonImmutable::now();
        $lesson = $this->lessons->lesson($sessionId);
        $seat = $lesson === null ? null : $this->access->seatFor($userId, $lesson);
        if ($lesson === null || $seat === null) {
            $consent = $lesson !== null && $this->access->waitsForConsent($userId, $lesson);
            throw ValidationException::withMessages(['lesson' => __($consent ? 'classroom::classroom.refused.consent' : 'classroom::classroom.errors.not_yours')]);
        }
        if (! $lesson->isOpen() || $now->greaterThan($lesson->endsAt->addMinutes(config('classroom.closes_after_min')))) {
            throw ValidationException::withMessages(['lesson' => __('classroom::classroom.errors.closed')]);
        }
        $opens = $lesson->startsAt->subMinutes(config('classroom.opens_before_min'));
        if ($now->lessThan($opens)) {
            throw ValidationException::withMessages(['lesson' => __('classroom::classroom.errors.too_early', ['before' => (int) config('classroom.opens_before_min'), 'minutes' => (int) ceil($now->diffInMinutes($opens))])]);
        }

        $chosen = ClassroomSettings::current()->provider;
        $room = Room::query()->firstOrCreate(['session_id' => $lesson->id], [
            'provider' => $chosen,
            'name' => Room::nameFor($this->tenant->require()->id, $lesson->id),
        ]);
        // A room made before the academy changed its provider (someone opened the class page early) follows the
        // new choice until the teacher has come in (the lesson is under way then and stays where it is).
        if ($room->provider !== $chosen && $room->started_at === null && $lesson->status === 'scheduled') {
            $room->update(['provider' => $chosen]);
        }

        // A LiveKit room on a server that no longer has LiveKit: the teacher's outside link still works.
        return $this->providers->get($this->providers->effective($room->provider))->ticket($room, $seat, $lesson)->withCamera($this->cameraOn($seat, $lesson));
    }

    /**
     * Someone is going in now (the class page's "go", the apps' and the portal's join): their ticket, and,
     * for an outside link, which tells us nothing itself, their join is recorded here (not a supervisor's),
     * so a lesson held on Meet or Zoom is not taken for one nobody came to.
     */
    public function enter(string $sessionId, string $userId, ?CarbonImmutable $now = null): JoinTicket
    {
        $now ??= CarbonImmutable::now();
        $ticket = $this->handle($sessionId, $userId, $now);
        if ($ticket->provider === 'external' && $ticket->seat->role !== 'observer') {
            $this->lessons->participantJoined($sessionId, $ticket->seat->identity, $now, 'external_link');
        }

        return $ticket;
    }

    /** A student comes in with the camera the academy chose (on, off, or off when under 18); everyone else with it on. */
    private function cameraOn(Seat $seat, LessonCard $lesson): bool
    {
        if ($seat->role !== 'student') {
            return true;
        }

        return match (ClassroomSettings::current()->student_camera) {
            'off' => false,
            'off_for_minors' => $lesson->studentId === null || ! $this->people->isMinor($lesson->studentId),
            default => true,
        };
    }
}
