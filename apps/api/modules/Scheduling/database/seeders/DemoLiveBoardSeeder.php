<?php

namespace Modules\Scheduling\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Identity\Contracts\DTOs\NewUser;
use Modules\Identity\Contracts\IdentityApi;
use Modules\Identity\Enums\Role;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\ApplyAttendanceDeadlines;
use Modules\Scheduling\Actions\RecordConnectionDrop;
use Modules\Scheduling\Actions\RecordParticipantJoin;
use Modules\Scheduling\Actions\ScheduleSession;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Domain\Models\Session;

/**
 * A believable evening for the live board, relative to when it runs: a late
 * teacher, a missing student, a weak connection, an unpaid session, sessions
 * running fine, two coming up and two already finished. Runs inside the
 * current academy. Re-run with `composer demo` once the times have passed.
 */
final class DemoLiveBoardSeeder extends Seeder
{
    public function run(IdentityApi $identity, CatalogApi $catalog, PeopleApi $people, ScheduleSession $schedule,
        RecordParticipantJoin $join, RecordConnectionDrop $drop, ApplyAttendanceDeadlines $deadlines): void
    {
        $now = CarbonImmutable::now()->startOfMinute();

        $subjects = [
            'tajweed' => $catalog->createSubject('تجويد', 'Tajweed'),
            'quran' => $catalog->createSubject('قرآن · حفظ', 'Quran memorisation'),
            'arabic' => $catalog->createSubject('لغة عربية', 'Arabic'),
            'english' => $catalog->createSubject('إنجليزي', 'English'),
            'grammar' => $catalog->createSubject('نحو', 'Arabic grammar'),
        ];

        $user = fn (string $email, string $name, Role $role, string $tz, ?string $phone = null) => $identity->createUser(new NewUser(
            name: $name, roles: [$role], email: $email, phoneE164: $phone, password: 'password', locale: 'ar', timezone: $tz,
        ));

        $teachers = [
            'mahmoud' => $user('mahmoud@demo.test', 'أ. محمود سالم', Role::Teacher, 'Africa/Cairo', '+201001230001'),
            'nour' => $user('nour@demo.test', 'أ. نور الهدى', Role::Teacher, 'Africa/Cairo', '+201001230002'),
            'sara' => $user('sara@demo.test', 'أ. سارة عبد الرحمن', Role::Teacher, 'Africa/Cairo', '+201001230003'),
            'khaled' => $user('khaled@demo.test', 'أ. خالد يوسف', Role::Teacher, 'Africa/Cairo', '+201001230004'),
            'ahmed' => $user('ahmed@demo.test', 'أ. أحمد فؤاد', Role::Teacher, 'Africa/Cairo', '+201001230005'),
            'mona' => $user('mona@demo.test', 'أ. منى إبراهيم', Role::Teacher, 'Asia/Amman', '+962790000006'),
        ];

        $students = [];
        foreach ([
            'omar' => ['عمر ناصر', 'Asia/Qatar', 'ناصر علي', '+97455000001', 'father'],
            'yousef' => ['يوسف خالد', 'Europe/London', 'خالد عمر', '+447700900002', 'father'],
            'maryam' => ['مريم أحمد', 'Asia/Riyadh', 'أحمد ناصر', '+966500000003', 'father'],
            'hamza' => ['حمزة علي', 'Asia/Dubai', 'علي حسين', '+971500000004', 'father'],
            'adam' => ['آدم مالك', 'Europe/London', 'مالك حسن', '+447700900005', 'father'],
            'layan' => ['ليان سعيد', 'Asia/Riyadh', 'سعيد الحربي', '+966500000006', 'father'],
            'salma' => ['سلمى رامي', 'America/New_York', 'رامي فؤاد', '+12125550107', 'father'],
            'zain' => ['زين حسام', 'Asia/Kuwait', 'منى حسام', '+96550000008', 'mother'],
            'ziad' => ['زياد عمرو', 'Asia/Kuwait', 'عمرو سالم', '+96550000009', 'father'],
            'rana' => ['رنا محمود', 'Asia/Riyadh', 'محمود سعد', '+966500000010', 'father'],
        ] as $key => [$name, $tz, $guardianName, $guardianPhone, $relationship]) {
            $students[$key] = $user("{$key}@demo.test", $name, Role::Student, $tz);
            $people->linkGuardian($user("{$key}.guardian@demo.test", $guardianName, Role::Guardian, $tz, $guardianPhone), $students[$key], $relationship);
        }

        $book = fn (string $teacher, string $student, string $subject, int $startOffset, int $minutes, string $type = 'regular', string $funding = 'funded') => $schedule->handle(new NewSession(
            teacherId: $teachers[$teacher], studentId: $students[$student], subjectId: $subjects[$subject],
            startsAt: $now->addMinutes($startOffset), durationMin: $minutes, anchorTimezone: 'Africa/Cairo', type: $type, funding: $funding,
        ));
        $at = fn (int $offset) => $now->addMinutes($offset);
        $in = fn (Session $s, string $userId, int $offset) => $join->handle($s->refresh(), $userId, $at($offset));

        // Finished earlier: completed, and a teacher who never came.
        $ziad = $book('khaled', 'ziad', 'grammar', -63, 45);
        $in($ziad, $teachers['khaled'], -64);
        $in($ziad, $students['ziad'], -63);
        $rana = $book('nour', 'rana', 'quran', -120, 30);
        $in($rana, $students['rana'], -121);

        // Needs someone now.
        $omar = $book('mahmoud', 'omar', 'tajweed', -3, 45);
        $in($omar, $students['omar'], -4);

        $yousef = $book('nour', 'yousef', 'quran', -3, 30);
        $in($yousef, $teachers['nour'], -5);

        $maryam = $book('sara', 'maryam', 'tajweed', -33, 45);
        $in($maryam, $teachers['sara'], -34);
        $in($maryam, $students['maryam'], -32);
        foreach ([[-14, 40], [-6, 25], [-2, 50]] as [$offset, $seconds]) {
            $drop->handle($maryam, $teachers['sara'], $seconds, $at($offset));
        }

        $book('sara', 'hamza', 'quran', 27, 30, funding: 'unfunded');

        // Running fine.
        $adam = $book('khaled', 'adam', 'arabic', -18, 45, 'makeup', 'makeup');
        $in($adam, $teachers['khaled'], -19);
        $in($adam, $students['adam'], -18);

        $layan = $book('ahmed', 'layan', 'english', -3, 30, 'trial', 'free');
        $in($layan, $teachers['ahmed'], -5);
        $in($layan, $students['layan'], -3);

        // Coming up.
        $book('mona', 'salma', 'arabic', 27, 45);
        $book('khaled', 'zain', 'grammar', 42, 45);

        // Close what is already due (the finished ones), as the per-minute sweeper would.
        $deadlines->handle($now);
    }
}
