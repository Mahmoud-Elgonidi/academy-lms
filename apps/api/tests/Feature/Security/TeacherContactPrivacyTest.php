<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Identity\Models\User;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\SaasPlan;

/*
 * Teachers never see how to reach a family (docs/architecture/03, section 11:
 * "إخفاء أرقام التواصل"): every demo teacher opens everything a teacher can
 * (the apps' API, the /teach screens, the class page, their trials) about
 * their own lessons, students, reports, homework and tickets, and no answer
 * may carry a student's, a guardian's or a lead's email or phone number.
 */

beforeEach(function () {
    SaasPlan::query()->create(['code' => 'pro', 'name_ar' => 'الاحتراف', 'name_en' => 'Pro', 'max_teachers' => 75, 'max_active_students' => 1000,
        'features' => ['crm', 'online_payments', 'import', 'custom_branding', 'payroll']]);
    $this->seed(DatabaseSeeder::class);
    $this->clearTenant();
});

it('never shows a teacher a family\'s email or phone number', function () {
    $db = DB::connection('pgsql_platform');
    $academy = (string) $db->table('academies')->where('slug', 'demo')->value('id');
    $families = $db->table('users as u')->where('u.academy_id', $academy)
        ->whereExists(fn ($q) => $q->from('user_roles as r')->whereColumn('r.user_id', 'u.id')->whereIn('r.role', ['student', 'guardian']))
        ->get(['u.email', 'u.phone_e164']);
    $leads = $db->table('leads')->where('academy_id', $academy)->get(['email', 'phone_e164']);
    $secrets = $families->concat($leads)->flatMap(fn ($c) => array_filter([$c->email, $c->phone_e164, $c->phone_e164 ? ltrim($c->phone_e164, '+') : null]))
        ->unique()->values()->all();
    expect(count($secrets))->toBeGreaterThan(10);

    $demo = Academy::query()->findOrFail($academy)->toTenantRef();
    $this->actAsTenant($demo);
    $teachers = User::query()->whereHas('roleRecords', fn ($q) => $q->where('role', 'teacher'))->get();
    $this->clearTenant();
    $apiRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => in_array('GET', $r->methods(), true) && str_starts_with($r->uri(), 'api/v1/') && ! str_starts_with($r->uri(), 'api/v1/family/'));
    $pages = ['teach', 'teach/today', 'teach/week', 'teach/students', 'teach/students/{enrollment}', 'teach/reports', 'teach/reports/{report}', 'teach/homework',
        'teach/availability', 'teach/time-off', 'teach/trials', 'teach/earnings', 'teach/performance', 'teach/recordings', 'teach/support', 'teach/profile', 'class/{session}'];

    $seen = 0;
    $leaks = [];
    foreach ($teachers as $teacher) {
        $one = fn (string $table, string $column = 'teacher_id') => $db->table($table)->where('academy_id', $academy)->where($column, $teacher->id)->orderByDesc('created_at')->value('id');
        $session = $db->table('sessions')->where('academy_id', $academy)->where('teacher_id', $teacher->id)->orderByRaw("status = 'completed' desc")->orderByDesc('starts_at')->value('id');
        $ids = array_filter([
            'session' => $session,
            'enrollment' => $one('enrollments'),
            'report' => $one('session_reports'),
            'submission' => $db->table('homework_submissions as s')->join('homework as h', 'h.id', '=', 's.homework_id')->where('h.teacher_id', $teacher->id)->value('s.id'),
            'ticket' => $one('tickets', 'requester_id'),
        ]);

        $this->actAsTenant($demo);
        $token = $teacher->createToken('privacy')->plainTextToken;
        $this->clearTenant();

        foreach ($apiRoutes as $route) {
            $uri = $route->uri();
            $params = $route->parameterNames();
            if (array_diff($params, array_keys($ids)) !== []) {
                continue;
            }
            foreach ($params as $param) {
                $uri = str_replace('{'.$param.'}', $ids[$param], $uri);
            }
            app('auth')->forgetGuards();
            $response = $this->withToken($token)->getJson('http://demo.lms.test/'.$uri);
            $seen += $response->isSuccessful() ? 1 : 0;
            foreach ($secrets as $secret) {
                if (str_contains((string) $response->getContent(), $secret)) {
                    $leaks[] = "{$teacher->email}: GET {$route->uri()} shows {$secret}";
                }
            }
        }

        $this->flushHeaders();
        app('auth')->shouldUse('web');
        foreach ($pages as $page) {
            preg_match_all('/\{(\w+)\}/', $page, $m);
            if (array_diff($m[1], array_keys($ids)) !== []) {
                continue;
            }
            $url = preg_replace_callback('/\{(\w+)\}/', fn ($x) => $ids[$x[1]], $page);
            app('auth')->forgetGuards();
            $this->flushSession();
            $response = $this->actingAs($teacher, 'web')->get('http://demo.lms.test/'.$url);
            $seen += $response->isSuccessful() ? 1 : 0;
            foreach ($secrets as $secret) {
                if (str_contains((string) $response->getContent(), $secret)) {
                    $leaks[] = "{$teacher->email}: GET /{$page} shows {$secret}";
                }
            }
        }
    }

    // The same check finds them where they belong: sales open a lead and see its number.
    $this->actAsTenant($demo);
    $sales = User::query()->where('email', 'sales@demo.test')->firstOrFail();
    $lead = $db->table('leads')->where('academy_id', $academy)->orderBy('created_at')->first(['id', 'phone_e164']);
    $this->clearTenant();
    app('auth')->forgetGuards();
    $this->flushSession();
    $control = $this->actingAs($sales, 'web')->get('http://demo.lms.test/admin/leads/'.$lead->id);

    expect($teachers->count())->toBeGreaterThan(2)
        ->and($seen)->toBeGreaterThan(100)
        ->and(array_values(array_unique($leaks)))->toBe([])
        ->and($control->getStatusCode())->toBe(200)
        ->and((string) $control->getContent())->toContain($lead->phone_e164);
});
