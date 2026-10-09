<?php

namespace Modules\Classroom\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Classroom\Actions\SaveClassroomSettings;
use Modules\Classroom\Actions\SaveMeetingLink;
use Modules\Classroom\Models\ClassroomSettings;

/**
 * The demo academy teaches in its own classroom (LiveKit, in docker
 * compose), and students under 18 come in with their camera off (they can
 * turn it on). Khaled also keeps a Meet link, the fallback the academy can
 * switch to under Settings → Classroom.
 */
final class DemoClassroomSeeder extends Seeder
{
    public function run(SaveMeetingLink $links, SaveClassroomSettings $settings): void
    {
        $settings->handle(ClassroomSettings::current()->provider, 'off_for_minors');
        if ($khaled = DB::table('users')->where('email', 'khaled@demo.test')->value('id')) {
            $links->handle($khaled, 'https://meet.google.com/noor-demo-khaled');
        }
    }
}
