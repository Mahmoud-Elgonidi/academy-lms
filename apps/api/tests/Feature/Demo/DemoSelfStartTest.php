<?php

use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCatchUpSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\SaasPlan;
use Modules\Quran\Texts\QuranText;
use Modules\Scheduling\Actions\Shifts\ApproveShiftProposal;
use Modules\Scheduling\Actions\Shifts\CounterShiftProposal;
use Modules\Scheduling\Database\Seeders\DemoOpenHoursSeeder;
use Modules\Scheduling\Database\Seeders\DemoShiftProposalsSeeder;
use Modules\Scheduling\Database\Seeders\DemoShiftsSeeder;

/*
 * The family starts a course on its own in the demo (docs/architecture/02,
 * section 27, J3): a weekly time is offered when it is free in its first four
 * weeks but one skipped for the demo's mid-term holiday or Nour's leave (no
 * lesson that day, said with the time), the Quran and Tajweed teachers work
 * evenings on more days (DemoOpenHoursSeeder) and Sara, who gave Jana (13)
 * her Tajweed trial, teaches middle school: Jana's mother can start the plans
 * of two and three lessons a week from the report, with Sara.
 */

beforeEach(function () {
    SaasPlan::query()->create(['code' => 'pro', 'name_ar' => 'الاحتراف', 'name_en' => 'Pro', 'max_teachers' => 75, 'max_active_students' => 1000,
        'features' => ['crm', 'online_payments', 'import', 'custom_branding', 'payroll', 'quran']]);
    app()->instance(QuranText::class, new QuranText(base_path('tests/Fixtures/none')));
});

/** In the demo academy, as its owner would read it. */
function selfStartDemo(): void
{
    test()->clearTenant();
    test()->actAsTenant(app(TenantDirectory::class)->bySlug('demo'));
}

function demoId(string $key): string
{
    return (string) DB::table('users')->where('email', "{$key}@demo.test")->value('id');
}

/** Jana's mother asks the API (as the app and the portal do), then back in the demo. */
function janaMother(string $path): array
{
    selfStartDemo();
    $token = User::query()->where('email', 'jana.mother@demo.test')->sole()->createToken('phone')->plainTextToken;
    test()->clearTenant();
    app('auth')->forgetGuards();
    $answer = test()->withToken($token)->getJson('http://demo.lms.test/api/v1/'.$path)->assertOk()->json('data');
    selfStartDemo();

    return $answer;
}

/** The weekdays (on Jana's mother's clock) on which a teacher can take Jana's Tajweed course on a 30-minute plan of so many lessons a week, and the suggested pattern. */
function janaDays(string $teacher, int $perWeek): array
{
    selfStartDemo();
    $jana = (string) DB::table('leads')->where('learner_name', 'جنى')->value('learner_id');
    $plan = (string) DB::table('plans')->where('lesson_format', 'private')->where('credit_scope', 'enrollment')
        ->where('session_minutes', 30)->where('sessions_per_week', $perWeek)->value('id');
    $slots = janaMother('family/start/slots?'.http_build_query(['learner_id' => $jana, 'subject_id' => DB::table('subjects')->where('name_en', 'Tajweed')->value('id'),
        'plan_id' => $plan, 'teacher_id' => demoId($teacher), 'timezone' => 'Africa/Cairo']));

    return [array_values(array_unique(array_column($slots['days'], 'weekday'))), $slots['suggested']];
}

/** Each teacher's schedule versions and proposals, to see that nothing more comes. */
function shiftState(): array
{
    selfStartDemo();

    return [DB::table('teacher_shift_schedules')->count(), DB::table('shift_proposals')->count(), DB::table('shift_proposals')->whereIn('status', ['submitted', 'countered'])->count()];
}

it('lets Jana\'s mother start Tajweed with Sara, two or three lessons a week', function () {
    $this->seed(DatabaseSeeder::class);

    // The report's «ابدأ بهذه الباقة» starts with Sara, the trial's teacher.
    selfStartDemo();
    $trial = DB::table('lead_trials as t')->join('leads as l', 'l.id', '=', 't.lead_id')->where('l.learner_name', 'جنى')->value('t.session_id');
    $start = janaMother("family/trials/{$trial}")['start'];
    expect($start['available'])->toBeTrue()
        ->and($start['teacher_id'])->toBe(demoId('sara'));

    [$days, $suggested] = janaDays('sara', 2);
    expect(count($days))->toBeGreaterThanOrEqual(2)->and($suggested)->toHaveCount(2);
    [$days, $suggested] = janaDays('sara', 3);
    expect(count($days))->toBeGreaterThanOrEqual(3)->and($suggested)->toHaveCount(3);
    // The other Tajweed teachers too, past the holiday and Nour's leave.
    foreach (['nour', 'mahmoud'] as $teacher) {
        expect(janaDays($teacher, 3)[1])->toHaveCount(3);
    }

    // The days came through the shift actions (the academy's offer, the teacher accepting), and the review screen keeps its proposals.
    selfStartDemo();
    $approved = DB::table('shift_proposals')->where('teacher_id', demoId('sara'))->where('status', 'approved')->sole();
    expect($approved->author_role)->toBe('admin')
        ->and(DB::table('shift_proposal_comments')->where('proposal_id', $approved->id)->pluck('body')->all())->toContain(DemoOpenHoursSeeder::NOTE)
        ->and(DB::table('shift_proposals')->where('teacher_id', demoId('sara'))->where('status', 'submitted')->value('note'))->toBe(DemoShiftProposalsSeeder::SARA_NOTE)
        ->and(DB::table('shift_proposals')->where('teacher_id', demoId('mona'))->where('status', 'countered')->value('note'))->toBe(DemoShiftProposalsSeeder::MONA_NOTE);

    // The next deploy adds nothing.
    $before = shiftState();
    $this->seed(DemoCatchUpSeeder::class);
    expect(shiftState())->toBe($before);
});

it('brings the days to a demo made before them, once, and leaves what the owner changed', function () {
    // A demo seeded before DemoOpenHoursSeeder: three teaching days, Sara for teens and adults.
    app()->instance(DemoOpenHoursSeeder::class, new class extends Seeder
    {
        public function run(): void {}
    });
    $this->seed(DatabaseSeeder::class);
    app()->forgetInstance(DemoOpenHoursSeeder::class);
    selfStartDemo();
    $sara = DB::table('teacher_profiles')->where('user_id', demoId('sara'))->first();
    app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData(demoId('sara'), $sara->gender, json_decode($sara->languages, true),
        DB::table('teacher_subjects')->where('teacher_id', demoId('sara'))->pluck('subject_id')->all(), $sara->dialect, ageGroups: ['teen', 'adults'],
        maxWeeklyMinutes: (int) $sara->max_weekly_min, maxDailyLessons: (int) $sara->max_daily_lessons));
    // The owner gave Nour other hours by hand.
    $owner = demoId('owner');
    $nourWeek = DemoShiftsSeeder::week([[6, '16:00', '22:00'], [2, '17:00', '22:00'], [4, '18:00', '21:00']], 'Africa/Cairo');
    $hand = app(CounterShiftProposal::class)->handle(demoId('nour'), $nourWeek, CarbonImmutable::now('Africa/Cairo')->startOfDay(), null, $owner);
    app(ApproveShiftProposal::class)->handle($hand->refresh(), demoId('nour'), 'teacher');
    $nourVersions = DB::table('teacher_shift_schedules')->where('teacher_id', demoId('nour'))->count();
    $this->clearTenant();

    $this->seed(DemoCatchUpSeeder::class);

    [$days] = janaDays('sara', 3);
    expect(count($days))->toBeGreaterThanOrEqual(3)
        ->and(json_decode(DB::table('teacher_profiles')->where('user_id', demoId('sara'))->value('age_groups'), true))->toBe(['middle', 'teen', 'adults'])
        ->and(DB::table('teacher_subjects')->where('teacher_id', demoId('sara'))->count())->toBe(2)
        ->and(DB::table('teacher_shift_schedules')->where('teacher_id', demoId('nour'))->count())->toBe($nourVersions);
    // Sara's and Mona's stories on the review screen are told again, with the new days kept in their weeks.
    foreach (['sara' => ['submitted', DemoShiftProposalsSeeder::SARA_NOTE], 'mona' => ['countered', DemoShiftProposalsSeeder::MONA_NOTE]] as $key => [$status, $note]) {
        $open = DB::table('shift_proposals')->where('teacher_id', demoId($key))->whereIn('status', ['submitted', 'countered'])->sole();
        expect([$open->status, $open->note])->toBe([$status, $note])
            ->and(DB::table('shift_proposal_comments')->where('proposal_id', $open->id)->count())->toBeGreaterThanOrEqual(1);
    }

    $after = shiftState();
    $this->seed(DemoCatchUpSeeder::class);
    expect(shiftState())->toBe($after);
});
