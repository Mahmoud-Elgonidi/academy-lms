<?php

namespace Modules\Classroom\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Authorization\ClassroomAccess;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\Room;
use Modules\Classroom\Video\JoinTicket;
use Modules\Classroom\Video\ProviderManager;
use Modules\Scheduling\Contracts\AttendanceApi;
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
    ) {}

    public function handle(string $sessionId, string $userId, ?CarbonImmutable $now = null): JoinTicket
    {
        $now ??= CarbonImmutable::now();
        $lesson = $this->lessons->lesson($sessionId);
        $seat = $lesson === null ? null : $this->access->seatFor($userId, $lesson);
        if ($lesson === null || $seat === null) {
            throw ValidationException::withMessages(['lesson' => __('classroom::classroom.errors.not_yours')]);
        }
        if (! $lesson->isOpen() || $now->greaterThan($lesson->endsAt->addMinutes(config('classroom.closes_after_min')))) {
            throw ValidationException::withMessages(['lesson' => __('classroom::classroom.errors.closed')]);
        }
        $opens = $lesson->startsAt->subMinutes(config('classroom.opens_before_min'));
        if ($now->lessThan($opens)) {
            throw ValidationException::withMessages(['lesson' => __('classroom::classroom.errors.too_early', ['minutes' => (int) ceil($now->diffInMinutes($opens))])]);
        }

        $room = Room::query()->firstOrCreate(['session_id' => $lesson->id], [
            'provider' => ClassroomSettings::current()->provider,
            'name' => Room::nameFor($this->tenant->require()->id, $lesson->id),
        ]);

        return $this->providers->get($room->provider)->ticket($room, $seat, $lesson);
    }
}
