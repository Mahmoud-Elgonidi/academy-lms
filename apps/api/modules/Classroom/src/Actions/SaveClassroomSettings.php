<?php

namespace Modules\Classroom\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Video\ProviderManager;

/**
 * The academy's video provider for lessons from now on (rooms already made
 * keep theirs; LiveKit only on a server that has it), and how students come in: camera on, off, or off when under
 * 18 (they can always switch it themselves).
 */
final class SaveClassroomSettings
{
    public function __construct(private readonly ProviderManager $providers) {}

    public function handle(string $provider, ?string $studentCamera = null): ClassroomSettings
    {
        Validator::make(['provider' => $provider, 'student_camera' => $studentCamera], [
            'provider' => ['required', Rule::in(ClassroomSettings::PROVIDERS)],
            'student_camera' => ['nullable', Rule::in(ClassroomSettings::STUDENT_CAMERA)],
        ])->validate();
        if (! in_array($provider, $this->providers->available(), true)) {
            throw ValidationException::withMessages(['provider' => __('classroom::classroom.errors.livekit_unavailable')]);
        }
        $settings = ClassroomSettings::current();
        $settings->fill(array_filter(['provider' => $provider, 'student_camera' => $studentCamera]))->save();

        return $settings;
    }
}
