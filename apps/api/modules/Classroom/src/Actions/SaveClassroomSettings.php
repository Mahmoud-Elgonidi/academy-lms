<?php

namespace Modules\Classroom\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Classroom\Models\ClassroomSettings;

/** The academy's video provider for lessons from now on (rooms already made keep theirs). */
final class SaveClassroomSettings
{
    public function handle(string $provider): ClassroomSettings
    {
        Validator::make(['provider' => $provider], ['provider' => ['required', Rule::in(ClassroomSettings::PROVIDERS)]])->validate();
        $settings = ClassroomSettings::current();
        $settings->fill(['provider' => $provider])->save();

        return $settings;
    }
}
