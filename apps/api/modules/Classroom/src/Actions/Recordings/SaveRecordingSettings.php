<?php

namespace Modules\Classroom\Actions\Recordings;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\Classroom\Models\Recording;
use Modules\SharedKernel\Audit\AuditTrail;
use Throwable;

/**
 * Whether the academy records lessons and how: off, the teacher starts it,
 * or every lesson whose people agreed; video, sound, or by age; how many days
 * a recording is kept (new ones; the ones made keep theirs); and who besides
 * the office watches. Turning it off stops what is running.
 */
final class SaveRecordingSettings
{
    public function __construct(
        private readonly StopRecording $stop,
        private readonly AuditTrail $audit,
    ) {}

    /** @param  array{recording: string, recording_media: string, recording_days: int|string, recording_family: bool, recording_teacher: bool}  $data */
    public function handle(array $data, string $byUserId): ClassroomSettings
    {
        $data = Validator::make($data, [
            'recording' => ['required', Rule::in(ClassroomSettings::RECORDING)],
            'recording_media' => ['required', Rule::in(ClassroomSettings::RECORDING_MEDIA)],
            'recording_days' => ['required', 'integer', 'between:'.ClassroomSettings::RECORDING_DAYS[0].','.ClassroomSettings::RECORDING_DAYS[1]],
            'recording_family' => ['required', 'boolean'],
            'recording_teacher' => ['required', 'boolean'],
        ], [], __('classroom::recordings.settings.fields'))->validate();

        $settings = DB::transaction(function () use ($data) {
            $settings = ClassroomSettings::current();
            $settings->fill([...$data, 'recording_days' => (int) $data['recording_days'], 'recording_family' => (bool) $data['recording_family'], 'recording_teacher' => (bool) $data['recording_teacher']])->save();
            $this->audit->record('classroom.recording_settings_saved', 'academy', null, [
                'recording' => $settings->recording, 'media' => $settings->recording_media, 'days' => $settings->recording_days,
            ]);

            return $settings;
        });

        if (! $settings->records()) {
            foreach (Recording::query()->running()->get() as $recording) {
                try {
                    $this->stop->handle($recording, $byUserId);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return $settings;
    }
}
