<?php

use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCatchUpSeeder;
use Database\Seeders\DemoGroupsSeeder;
use Database\Seeders\E2eResetSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Learning\Actions\SubmitGroupReport;
use Modules\Learning\Models\SessionReport;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Platform\Models\SaasPlan;
use Modules\Scheduling\Actions\Groups\ExcuseSeat;
use Modules\Scheduling\Domain\Models\Session;
use Modules\SharedKernel\Tenancy\TenantContext;

/*
 * Group lessons in the demo (docs/architecture/02, section 25): Khaled's
 * running Arabic group of adults (3 of 5, two weeks of lessons held), Mona's
 * children's group (a child sees how many are with her) and her Quran group
 * forming with one of the four it waits for, on group plans at about 60% of
 * the private price, paid at the group rate. Ziad stays one-to-one. A demo
 * made before groups gets them from DemoCatchUpSeeder, once.
 */

function groupsDemo(): array
{
    return app(TenantContext::class)->run(app(TenantDirectory::class)->bySlug('demo'), function () {
        $id = fn (string $key) => DB::table('users')->where('email', "{$key}@demo.test")->value('id');
        $groups = DB::table('lesson_groups as g')->join('users as t', 't.id', '=', 'g.teacher_id')
            ->orderBy('t.email')->orderBy('g.status')->get(['g.id', 't.email', 'g.status', 'g.capacity', 'g.min_to_start', 'g.started_on', 'g.age_group'])
            ->map(fn ($g) => [
                'teacher' => $g->email, 'status' => $g->status, 'capacity' => $g->capacity, 'min' => $g->min_to_start, 'ages' => $g->age_group,
                'members' => DB::table('group_members as m')->join('users as u', 'u.id', '=', 'm.student_id')->where('m.group_id', $g->id)->whereNull('m.left_on')->orderBy('u.email')->pluck('u.email')->all(),
                'held' => DB::table('sessions')->where('group_id', $g->id)->whereIn('status', ['completed', 'student_absent'])->count(),
                'ahead' => DB::table('sessions')->where('group_id', $g->id)->where('status', 'scheduled')->where('starts_at', '>', now())->count(),
                'started' => $g->started_on,
            ])->all();

        return [
            'groups' => $groups,
            'plans' => DB::table('plans')->where('lesson_format', 'group')->count(),
            'teachers' => DB::table('teacher_profiles')->where('accepts_groups', true)->join('users', 'users.id', '=', 'teacher_profiles.user_id')->orderBy('email')->pluck('max_group_size', 'email')->all(),
            'rates' => DB::table('rate_card_lines')->where('session_format', 'group')->count(),
            'ziad' => DB::table('group_members')->where('student_id', $id('ziad'))->count(),
            'kid_consent' => DB::table('guardian_consents')->where('student_id', $id('lina'))->whereNull('withdrawn_at')->count(),
            'nader_discount' => DB::table('invoice_lines as l')->join('subscriptions as su', 'su.id', '=', 'l.subscription_id')->join('enrollments as e', 'e.id', '=', 'su.enrollment_id')
                ->where('e.student_id', $id('nader'))->where('l.kind', 'discount')->count(),
        ];
    });
}

function proPlan(): void
{
    SaasPlan::query()->create(['code' => 'pro', 'name_ar' => 'الاحتراف', 'name_en' => 'Pro', 'max_teachers' => 75, 'max_active_students' => 1000,
        'features' => ['crm', 'online_payments', 'import', 'custom_branding', 'payroll']]);
}

it('seeds a running group of adults, a children\'s group and one forming, through the real actions', function () {
    proPlan();
    $this->seed(DatabaseSeeder::class);
    $this->clearTenant();
    $demo = groupsDemo();

    [$khaled, $monaKids, $monaQuran] = [$demo['groups'][0], ...collect($demo['groups'])->where('teacher', 'mona@demo.test')->sortBy('status', SORT_STRING, true)->values()->all()];
    expect($khaled)->toMatchArray(['teacher' => 'khaled@demo.test', 'status' => 'running', 'capacity' => 5, 'ages' => 'adults',
        'members' => ['dana@demo.test', 'nader@demo.test', 'tamer@demo.test']])
        ->and($khaled['held'])->toBeGreaterThanOrEqual(3)
        ->and($khaled['ahead'])->toBeGreaterThan(0)
        ->and(CarbonImmutable::parse($khaled['started'])->diffInDays(CarbonImmutable::now()))->toBeGreaterThanOrEqual(14)
        ->and($monaKids)->toMatchArray(['status' => 'running', 'capacity' => 6, 'ages' => 'kids', 'members' => ['lina@demo.test', 'tala@demo.test', 'yahya@demo.test']])
        ->and($monaKids['held'])->toBeGreaterThan(0)
        ->and($monaQuran)->toMatchArray(['status' => 'forming', 'capacity' => 6, 'min' => 4, 'members' => ['sama@demo.test'], 'held' => 0, 'ahead' => 0])
        ->and($demo['teachers'])->toBe(['khaled@demo.test' => 5, 'mona@demo.test' => 6])
        ->and($demo['plans'])->toBe(3)
        ->and($demo['rates'])->toBe(2)
        ->and($demo['ziad'])->toBe(0)
        ->and($demo['kid_consent'])->toBe(1)
        ->and($demo['nader_discount'])->toBeGreaterThan(0);

    // A group plan costs about 60% of the private one of its shape, in every region.
    app(TenantContext::class)->run(app(TenantDirectory::class)->bySlug('demo'), function () {
        $price = fn (string $format) => DB::table('plan_prices as p')->join('plans as pl', 'pl.id', '=', 'p.plan_id')->join('price_regions as r', 'r.id', '=', 'p.price_region_id')
            ->where('pl.lesson_format', $format)->where('pl.credit_scope', 'enrollment')->where('pl.session_minutes', 45)->where('pl.sessions_per_week', 2)
            ->pluck('p.amount_minor', 'r.currency');
        $private = $price('private');
        foreach ($price('group') as $currency => $amount) {
            expect($amount / $private[$currency])->toBeGreaterThan(0.55)->toBeLessThan(0.65);
        }
    });

    // On the next deploy, nothing new.
    $this->seed(DemoCatchUpSeeder::class);
    expect(groupsDemo())->toBe($demo);

    // The end-to-end reset names the children's group, a lesson to excuse, a report to write and the family's sign-ins,
    // and puts back what a run changed: an excused seat, a report handed in.
    $fixtures = function (): array {
        Artisan::call('db:seed', ['--class' => E2eResetSeeder::class, '--force' => true]);
        preg_match('/E2E_FIXTURES (\{.*\})/', Artisan::output(), $m);
        $this->clearTenant();

        return json_decode($m[1], true)['group'];
    };
    $group = $fixtures();
    expect($group)->toMatchArray(['guardian' => 'lina.guardian@demo.test', 'student' => 'lina@demo.test', 'teacher' => 'mona@demo.test'])
        ->and($group['lesson_id'])->not->toBeNull()
        ->and($group['report_id'])->not->toBeNull();

    $inDemo = fn (callable $do) => app(TenantContext::class)->run(app(TenantDirectory::class)->bySlug('demo'), $do);
    $inDemo(function () use ($group) {
        $lina = DB::table('users')->where('email', 'lina@demo.test')->value('id');
        app(ExcuseSeat::class)->handle(Session::query()->findOrFail($group['lesson_id']), $lina, 'travel', null, null);
        $report = SessionReport::query()->findOrFail($group['report_id']);
        $rows = SessionReport::query()->where('session_id', $report->session_id)->get()->map(fn (SessionReport $r) => $r->kind === 'lesson'
            ? ['student_id' => $r->student_id, 'engagement' => 5, 'overall' => 5] : ['student_id' => $r->student_id, 'absence_note' => 'غاب'])->all();
        app(SubmitGroupReport::class)->handle($report, $report->teacher_id, ['items' => []], $rows);
        expect($report->refresh()->status)->toBe('submitted');
    });

    $again = $fixtures();
    expect($again)->toBe($group);
    $inDemo(function () use ($group) {
        $lina = DB::table('users')->where('email', 'lina@demo.test')->value('id');
        $seat = DB::table('session_participants')->where('session_id', $group['lesson_id'])->where('user_id', $lina)->first();
        expect($seat->outcome)->toBeNull()->and($seat->is_active)->toBeTrue()
            ->and(SessionReport::query()->findOrFail($group['report_id'])->status)->toBeIn(['due', 'overdue']);
    });
});

it('brings group lessons to a demo made before them, once', function () {
    proPlan();
    $this->app->instance(DemoGroupsSeeder::class, new class extends DemoGroupsSeeder
    {
        public function run(): void {}
    });
    $this->seed(DatabaseSeeder::class);
    $this->clearTenant();
    $this->app->forgetInstance(DemoGroupsSeeder::class);
    expect(groupsDemo())->toMatchArray(['groups' => [], 'plans' => 0, 'teachers' => []]);

    $this->seed(DemoCatchUpSeeder::class);
    $after = groupsDemo();
    expect(collect($after['groups'])->pluck('status')->sort()->values()->all())->toBe(['forming', 'running', 'running'])
        ->and($after['plans'])->toBe(3)
        ->and($after['rates'])->toBe(2)
        ->and($after['teachers'])->toBe(['khaled@demo.test' => 5, 'mona@demo.test' => 6])
        ->and($after['groups'][0]['held'])->toBeGreaterThanOrEqual(3);

    $this->seed(DemoCatchUpSeeder::class);
    expect(groupsDemo())->toBe($after);
});
