<?php

namespace Modules\Classroom\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Classroom\Actions\Recordings\GiveRecordingConsent;
use Modules\Classroom\Actions\Recordings\RecordRecordingConsent;
use Modules\Classroom\Actions\Recordings\SaveRecordingSettings;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Models\RecordingAccessLog;
use Modules\Classroom\Models\Room;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The demo academy records lessons when the teacher starts it: Nour and
 * Sara agreed themselves, Khaled's contract has the clause (recorded by the
 * office); Yousef's and Maryam's guardians agreed, Ziad's has not yet. Their
 * last lessons have recordings (a short clip made here, as the recorder would
 * leave it): some opened by the family or the office, one held over a
 * dispute, one that never recorded, and one whose days were up.
 */
final class DemoRecordingsSeeder extends Seeder
{
    public function run(SaveRecordingSettings $settings, GiveRecordingConsent $give, RecordRecordingConsent $paper, TenantContext $tenants): void
    {
        $id = fn (string $email) => DB::table('users')->where('email', $email)->value('id');
        $owner = (string) $id('owner@demo.test');
        $settings->handle(['recording' => 'manual', 'recording_media' => 'by_age', 'recording_days' => 30, 'recording_family' => true, 'recording_teacher' => true], $owner);

        foreach (['nour@demo.test', 'sara@demo.test'] as $teacher) {
            if ($t = $id($teacher)) {
                $give->handle($t, $t);
            }
        }
        if ($khaled = $id('khaled@demo.test')) {
            $paper->handle($khaled, $owner, 'بند التسجيل في عقد المدرس، اتوقّع في سبتمبر 2026');
        }
        foreach (['yousef' => 'yousef.guardian', 'maryam' => 'maryam.guardian'] as $student => $guardian) {
            if (($s = $id("{$student}@demo.test")) && ($g = $id("{$guardian}@demo.test"))) {
                try {
                    $give->handle($s, $g);
                } catch (AuthorizationException) {
                    $give->handle($s, $s);    // old enough to agree for themself
                }
            }
        }

        $academy = $tenants->require()->id;
        $video = (string) file_get_contents(__DIR__.'/media/demo-lesson.mp4');
        foreach (['yousef' => [$id('yousef.guardian@demo.test'), $owner], 'maryam' => [$id('maryam.guardian@demo.test')]] as $student => $watchers) {
            $lessons = DB::table('sessions as s')
                ->join('session_participants as p', fn ($j) => $j->on('p.session_id', '=', 's.id')->on('p.academy_id', '=', 's.academy_id'))
                ->where('p.user_id', $id("{$student}@demo.test"))->where('p.role', 'student')->where('s.status', 'completed')
                ->orderByDesc('s.starts_at')->limit(4)->get(['s.id', 's.starts_at', 's.ends_at']);
            foreach ($lessons as $i => $lesson) {
                $recording = $this->recording($academy, $lesson, $video, $i);
                match (true) {
                    $student === 'maryam' && $i === 1 => $recording->update(['held_at' => now(), 'held_by' => $owner, 'hold_reason' => 'الأسرة معترضة على طريقة شرح الدرس، والمراجعة لسه مخلصتش']),
                    $student === 'maryam' && $i === 2 => $recording->forceFill(['status' => 'failed', 'size' => null, 'duration_sec' => null, 'expires_at' => null, 'error' => 'could not connect to the room'])->save(),
                    $student === 'yousef' && $i === 3 => $this->expire($recording),
                    default => null,
                };
                if ($i === 0) {
                    foreach (array_filter($watchers) as $n => $watcher) {
                        RecordingAccessLog::query()->create(['recording_id' => $recording->id, 'user_id' => $watcher, 'as' => $n === 0 ? 'family' : 'staff',
                            'ip' => '127.0.0.1', 'user_agent' => 'Demo', 'opened_at' => CarbonImmutable::parse($lesson->ends_at)->addHours(3 + $n)]);
                    }
                }
            }
        }
    }

    /** A recording of the lesson as the recorder leaves it: the clip in the academy's folder, ready, with its days. */
    private function recording(string $academy, object $lesson, string $video, int $n): Recording
    {
        $room = Room::query()->firstOrCreate(['session_id' => $lesson->id], ['provider' => 'livekit', 'name' => Room::nameFor($academy, $lesson->id),
            'started_at' => $lesson->starts_at, 'finished_at' => $lesson->ends_at]);
        $id = (string) Str::uuid7();
        $starts = CarbonImmutable::parse($lesson->starts_at)->addMinutes(2);
        $path = Recording::pathFor($academy, $lesson->id, $id, 'video');
        Storage::disk('local')->put($path, $video);

        return Recording::query()->forceCreate([
            'id' => $id, 'session_id' => $lesson->id, 'room_id' => $room->id, 'provider' => 'livekit', 'egress_id' => 'EG_demo_'.Str::random(10),
            'media' => 'video', 'status' => 'ready', 'started_at' => $starts, 'ended_at' => $starts->addSeconds(20), 'duration_sec' => 20,
            'size' => strlen($video), 'disk' => 'local', 'path' => $path, 'mime' => 'video/mp4', 'expires_at' => $starts->addSeconds(20)->addDays(30),
            'created_at' => $starts, 'updated_at' => $starts->addSeconds(20),
        ]);
    }

    /** Its days were up: the file went, the row stays. */
    private function expire(Recording $recording): void
    {
        Storage::disk('local')->delete($recording->path);
        $recording->forceFill(['status' => 'deleted', 'expires_at' => now()->subDay(), 'deleted_at' => now()->subDay(), 'delete_reason' => 'expired'])->save();
    }
}
