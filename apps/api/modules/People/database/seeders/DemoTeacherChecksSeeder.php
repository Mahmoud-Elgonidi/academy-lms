<?php

namespace Modules\People\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\People\Actions\Checks\AddTeacherCheck;
use Modules\People\Actions\Checks\DecideTeacherCheck;
use Modules\People\Actions\Checks\RemindTeacherChecks;
use Modules\People\Actions\Checks\SaveMinorCheckRule;

/**
 * The demo teachers' vetting, through the Actions at their own times: the
 * academy asks for a valid criminal record check before anyone teaches
 * under-18s. Mahmoud, Sara, Ahmed and Mona are cleared (Sara's has no end
 * date); Nour's runs out in under three weeks (she was reminded); Khaled
 * sent his two days ago and it waits for the leads; Heba's scan was turned
 * down as unreadable, so she has none; Mona's certificate also waits. The
 * scan is our own picture (files/demo-check.html), plainly a demo.
 */
final class DemoTeacherChecksSeeder extends Seeder
{
    public function run(AddTeacherCheck $add, DecideTeacherCheck $decide, SaveMinorCheckRule $rule, RemindTeacherChecks $remind): void
    {
        $id = fn (string $key) => DB::table('users')->where('email', "{$key}@demo.test")->value('id');
        $owner = $id('owner');
        if ($owner === null) {
            return;
        }
        $now = CarbonImmutable::now();
        $at = function (CarbonImmutable $when, callable $do) {
            CarbonImmutable::setTestNow($when);
            try {
                return $do();
            } finally {
                CarbonImmutable::setTestNow();
            }
        };
        $scan = function (): UploadedFile {
            $copy = tempnam(sys_get_temp_dir(), 'check');
            copy(__DIR__.'/files/demo-check.jpg', $copy);

            return new UploadedFile($copy, 'فيش.jpg', 'image/jpeg', null, true);
        };
        // A teacher sends one, and (some days later) the office checks it against the original.
        $send = function (string $key, string $kind, CarbonImmutable $sent, ?string $reference, ?CarbonImmutable $issued) use ($id, $add, $scan, $at) {
            $teacher = $id($key);

            return $teacher === null ? null : $at($sent, fn () => $add->handle($teacher, $kind, $reference, $issued, null, $scan(), $teacher));
        };
        $verify = fn ($check, CarbonImmutable $when, ?CarbonImmutable $until) => $check === null ? null
            : $at($when, fn () => $decide->handle($check->id, true, $until, null, $owner));

        $rule->handle(true);

        $verify($send('mahmoud', 'criminal_record', $now->subMonths(2), 'EG-2026-118204', $now->subMonths(2)->subDays(4)), $now->subMonths(2)->addDay(), $now->addMonths(10));
        $verify($send('mahmoud', 'identity', $now->subMonths(2), null, null), $now->subMonths(2)->addDay(), null);
        $verify($send('sara', 'criminal_record', $now->subMonths(5), 'EG-2026-044871', $now->subMonths(5)->subWeek()), $now->subMonths(5)->addDays(2), null);
        $verify($send('ahmed', 'criminal_record', $now->subMonth(), 'EG-2026-207733', $now->subMonth()->subDays(3)), $now->subMonth()->addDay(), $now->addMonths(11));
        $verify($send('mona', 'criminal_record', $now->subMonths(3), 'SY-2026-5521', $now->subMonths(3)->subDays(10)), $now->subMonths(3)->addDays(2), $now->addMonths(9));
        $send('mona', 'qualification', $now->subDays(1)->setTime(19, 20), 'IJ-QR-2019-77', $now->subYears(7));
        // Nour's runs out in under three weeks: the hourly reminder told her and the leads.
        $verify($send('nour', 'criminal_record', $now->subYear()->addDays(18), 'EG-2025-301190', $now->subYear()->addDays(15)), $now->subYear()->addDays(19), $now->addDays(18));
        $at($now->subHour(), fn () => $remind->handle());
        // Khaled's waits for the leads.
        $send('khaled', 'criminal_record', $now->subDays(2)->setTime(21, 5), 'EG-2026-298016', $now->subDays(6));
        // Heba's scan could not be read.
        if ($heba = $send('heba', 'criminal_record', $now->subDays(9)->setTime(17, 40), null, null)) {
            $at($now->subDays(8)->setTime(11, 0), fn () => $decide->handle($heba->id, false, null, 'الصورة غير واضحة ولا يظهر فيها الرقم ولا التاريخ. أرسليها مرة أخرى بصورة أوضح أو بملف PDF.', $owner));
        }
    }
}
