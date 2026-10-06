<?php

namespace Modules\Classroom\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Classroom\Actions\SaveMeetingLink;

/**
 * The demo academy teaches in its own classroom (LiveKit, in docker
 * compose). Khaled also keeps a Meet link, the fallback the academy can
 * switch to under Settings → Classroom.
 */
final class DemoClassroomSeeder extends Seeder
{
    public function run(SaveMeetingLink $links): void
    {
        if ($khaled = DB::table('users')->where('email', 'khaled@demo.test')->value('id')) {
            $links->handle($khaled, 'https://meet.google.com/noor-demo-khaled');
        }
    }
}
