<?php

namespace Modules\Classroom\Actions;

use Illuminate\Support\Facades\Validator;
use Modules\Classroom\Models\MeetingLink;

/** A teacher's own Zoom or Meet link; empty removes it. */
final class SaveMeetingLink
{
    public function handle(string $teacherId, ?string $url): void
    {
        $url = trim((string) $url);
        if ($url === '') {
            MeetingLink::query()->where('teacher_id', $teacherId)->delete();

            return;
        }
        Validator::make(['url' => $url], ['url' => ['url:https', 'max:500']], [], ['url' => __('classroom::classroom.link.label')])->validate();

        MeetingLink::query()->updateOrCreate(['teacher_id' => $teacherId], ['url' => $url]);
    }
}
