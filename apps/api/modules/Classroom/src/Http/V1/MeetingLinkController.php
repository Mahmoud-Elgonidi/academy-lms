<?php

namespace Modules\Classroom\Http\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Actions\SaveMeetingLink;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\MeetingLink;

/** The teacher's own Zoom or Meet link, for the apps and the portal (the same as "Your link" on /teach's today page). */
final class MeetingLinkController
{
    /**
     * The teacher's lesson link.
     *
     * Their own Zoom or Meet link, and whether the academy's lessons run on outside links now (then their families
     * join through it, and a lesson cannot open without it).
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->view((string) $request->user()->getAuthIdentifier()));
    }

    /**
     * Save the teacher's lesson link.
     *
     * An https link (Zoom, Meet, ...), 500 characters at most; an empty one removes it.
     */
    public function update(Request $request, SaveMeetingLink $save): JsonResponse
    {
        $request->validate(['url' => ['present', 'nullable', 'string', 'max:500']]);
        $teacher = (string) $request->user()->getAuthIdentifier();
        $save->handle($teacher, $request->input('url'));

        return response()->json($this->view($teacher));
    }

    /** @return array<string, mixed> */
    private function view(string $teacherId): array
    {
        return [
            /** The teacher's link, or null when they have none. */
            'url' => MeetingLink::query()->where('teacher_id', $teacherId)->value('url'),
            /** Whether the academy's lessons run on each teacher's outside link now. */
            'used' => ClassroomSettings::current()->provider === 'external',
            'label' => __('classroom::classroom.link.label'),
            'hint' => __('classroom::classroom.link.hint'),
        ];
    }
}
