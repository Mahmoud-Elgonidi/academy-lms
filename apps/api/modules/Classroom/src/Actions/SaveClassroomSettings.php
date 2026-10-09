<?php

namespace Modules\Classroom\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Classroom\Models\ClassroomSettings;

/**
 * The academy's video provider for lessons from now on (rooms already made
 * keep theirs), and how students come in: camera on, off, or off when under
 * 18 (they can always switch it themselves).
 */
final class SaveClassroomSettings
{
    public function handle(string $provider, ?string $studentCamera = null): ClassroomSettings
    {
        Validator::make(['provider' => $provider, 'student_camera' => $studentCamera], [
            'provider' => ['required', Rule::in(ClassroomSettings::PROVIDERS)],
            'student_camera' => ['nullable', Rule::in(ClassroomSettings::STUDENT_CAMERA)],
        ])->validate();
        $settings = ClassroomSettings::current();
        $settings->fill(array_filter(['provider' => $provider, 'student_camera' => $studentCamera]))->save();

        return $settings;
    }
}
