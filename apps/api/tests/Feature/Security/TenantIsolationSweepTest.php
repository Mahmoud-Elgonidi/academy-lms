<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\PeopleApi;
use Modules\Platform\Models\SaasPlan;

/*
 * Tenant isolation, swept (docs/architecture/03, section 11, "عزل المستأجرين"):
 * the demo academy with everything in it, and a second academy whose staff,
 * teacher and family try every way in to the demo's records. Every API route
 * that takes a record (and every family route, with the demo's child as the
 * learner), every panel page and file route with a record in its address,
 * the demo's own signed links opened on the other academy's host, and the
 * demo's live channels. A route that takes a record must refuse (403, 404,
 * 410 or 422), nothing may fail (5xx), and no answer may carry a name, an
 * email or an invoice number of the demo's.
 */

const REFUSED = [401, 403, 404, 410, 422];

// Family routes about the person's own account, whichever learner is asked for.
const OWN_FAMILY = ['api/v1/family/learners', 'api/v1/family/consents', 'api/v1/family/invoices'];

beforeEach(function () {
    SaasPlan::query()->create(['code' => 'pro', 'name_ar' => 'الاحتراف', 'name_en' => 'Pro', 'max_teachers' => 75, 'max_active_students' => 1000,
        'features' => ['crm', 'online_payments', 'import', 'custom_branding', 'payroll']]);
    $this->seed(DatabaseSeeder::class);
    $this->clearTenant();

    $platform = DB::connection('pgsql_platform');
    $this->demo = (string) $platform->table('academies')->where('slug', 'demo')->value('id');
    $one = fn (string $table, ?Closure $where = null) => (string) $platform->table($table)->where('academy_id', $this->demo)->when($where, $where)->orderBy('id')->value('id');
    $user = fn (string $email) => (string) $platform->table('users')->where('academy_id', $this->demo)->where('email', $email)->value('id');

    // One record of each kind from the demo academy.
    $this->ids = array_filter([
        'enrollment' => $one('enrollments'),
        'homework' => $one('homework'),
        'invoice' => $one('invoices', fn ($q) => $q->where('status', 'issued')),
        'ticket' => $one('tickets', fn ($q) => $q->whereExists(fn ($e) => $e->from('ticket_messages as m')->whereColumn('m.ticket_id', 'tickets.id')->whereNotNull('m.file_path'))),
        'message' => $one('ticket_messages', fn ($q) => $q->whereNotNull('file_path')),
        'person' => $user('yousef@demo.test'),
        'student' => $user('yousef@demo.test'),
        'recording' => $one('recordings'),
        'report' => $one('session_reports'),
        'session' => $one('sessions'),
        'submission' => $one('homework_submissions'),
        'timeOff' => $one('teacher_time_off'),
        'trial' => $one('lead_trials'),
        'lead' => $one('leads'),
        'teacher' => $user('khaled@demo.test'),
        'proposal' => $one('shift_proposals'),
        'proof' => $one('manual_payment_proofs'),
        'resource' => $one('node_resources'),
        'checkout' => $one('payment_checkouts'),
        'user' => $user('yousef.guardian@demo.test'),
        'program' => $one('programs'),
        // The newer doors: conversations, the games, privacy requests, a teacher's vetting scans.
        'conversation' => $one('conversations'),
        'creature' => (string) $platform->table('creature_finds')->where('academy_id', $this->demo)->orderBy('id')->value('creature_key'),
        'play' => $one('game_plays'),
        'id' => $one('data_requests'),
        'request' => $one('data_requests', fn ($q) => $q->where('kind', 'export')) ?: $one('data_requests'),
        'subject' => $user('yousef@demo.test'),
        'check' => $one('teacher_checks', fn ($q) => $q->whereNotNull('file_path')),
        'node' => $one('curriculum_nodes', fn ($q) => $q->where('kind', 'lesson')),
    ]);
    $this->game = (string) $platform->table('games')->orderBy('key')->value('key');
    // A message and its own ticket.
    if (isset($this->ids['message'])) {
        $this->ids['ticket'] = (string) $platform->table('ticket_messages')->where('id', $this->ids['message'])->value('ticket_id');
    }

    // What must never show up on the other academy: the demo's people and invoice numbers
    // (not the names of its role accounts, which are words any screen may use: "ولي أمر").
    $roleWords = ['scheduler@demo.test', 'accountant@demo.test', 'guardian@demo.test', 'student@demo.test'];
    $this->secrets = $platform->table('users')->where('academy_id', $this->demo)->whereNotNull('email')->pluck('email')
        ->merge($platform->table('users')->where('academy_id', $this->demo)->whereNotIn('email', $roleWords)->pluck('name')->filter(fn ($n) => mb_strlen((string) $n) >= 6))
        ->merge($platform->table('invoices')->where('academy_id', $this->demo)->whereNotNull('number')->pluck('number'))
        ->unique()->values()->all();

    // The other academy and its people.
    $this->noor = $this->createAcademy(['slug' => 'noor']);
    $this->actAsTenant($this->noor);
    $make = fn (Role ...$roles) => User::factory()->withRole(...$roles)->create();
    $this->people = [
        'owner' => $make(Role::Owner, Role::Admin),
        'accountant' => $make(Role::Accountant),
        'scheduler' => $make(Role::Scheduler),
        'supervisor' => $make(Role::Supervisor),
        'sales' => $make(Role::Sales),
        'teacher' => $make(Role::Teacher),
        'guardian' => $make(Role::Guardian),
        'student' => $make(Role::Student),
    ];
    app(PeopleApi::class)->linkGuardian($this->people['guardian']->id, $this->people['student']->id);
    $this->clearTenant();

});

/**
 * What is wrong with an answer, if anything.
 *
 * @param  list<string>  $secrets
 */
function sweepProblem(TestResponse $response, bool $mustRefuse, array $secrets): ?string
{
    $status = $response->getStatusCode();
    $body = (string) $response->getContent();
    $leaks = array_values(array_filter($secrets, fn (string $s) => $s !== '' && str_contains($body, $s)));
    // A page that sends the browser back on its own host (a refused form, the sign-in) refuses too; one that sends it away does not.
    $back = $status === 302 && str_starts_with((string) $response->headers->get('Location'), 'http://noor.lms.test');

    return match (true) {
        $status >= 500 => 'failed ('.$status.')',
        $leaks !== [] => 'shows '.implode(', ', array_slice($leaks, 0, 3)),
        $mustRefuse && ! in_array($status, REFUSED, true) && ! $back => 'answered '.$status,
        default => null,
    };
}

/** A body that gets past the usual checks, so a write reaches its record. */
function sweepBody(): array
{
    return [
        'reason' => 'illness', 'version' => 1, 'note' => 'checking', 'notes' => 'checking', 'level' => 'beginner', 'text' => 'checking',
        'body' => 'checking', 'message' => 'checking', 'lessons' => 1, 'mark' => 'good', 'feedback' => 'checking', 'stars' => 5,
        'rating' => 5, 'summary' => 'checking', 'agree' => true, 'scope' => 'recording', 'kind' => 'other', 'subject' => 'checking',
        'starts_at' => now()->addDays(3)->toIso8601String(), 'confirm' => true, 'accept' => true,
    ];
}

it('keeps every record of one academy out of reach of another academy\'s people', function () {
    $routes = collect(Route::getRoutes()->getRoutes());
    $answers = [];
    $note = function (string $who, string $what, TestResponse $response, bool $mustRefuse) use (&$answers) {
        $answers[] = [$who, $what, $response->getStatusCode(), sweepProblem($response, $mustRefuse, $this->secrets), (string) $response->headers->get('Location'), mb_substr((string) $response->getContent(), 0, 400)];
    };

    // 1. The apps' API: every route, as each person of the other academy, with the demo's records and learner.
    foreach ($this->people as $role => $person) {
        $this->actAsTenant($this->noor);
        $token = $person->createToken('sweep')->plainTextToken;
        $this->clearTenant();

        foreach ($routes->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/')) as $route) {
            $params = $route->parameterNames();
            if (array_diff($params, array_keys($this->ids)) !== [] || in_array($route->uri(), ['api/v1/realtime/auth', 'api/v1/auth/logout'], true)) {
                continue;   // a provider's name, the demo has none of it, or it would end this token
            }
            $uri = $route->uri();
            foreach ($params as $param) {
                $uri = str_replace(['{'.$param.'}', '{'.$param.'?}'], $this->ids[$param], $uri);
            }
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
            $query = 'learner_id='.$this->ids['student'];
            app('auth')->forgetGuards();
            $response = $method === 'GET'
                ? $this->withToken($token)->getJson('http://noor.lms.test/'.$uri.'?'.$query)
                : $this->withToken($token)->json($method, 'http://noor.lms.test/'.$uri.'?'.$query, sweepBody() + ['learner_id' => $this->ids['student']]);
            $note($role, $method.' '.$uri, $response, $params !== [] || (str_contains($route->uri(), 'family/') && ! in_array($route->uri(), OWN_FAMILY, true)));
        }
    }

    // 1b. The newer doors that take the record in their body (the journey, its chest, station rounds, cheers,
    // the games, messages, support, safeguarding, privacy), with bodies that get past their checks.
    $today = now()->toDateString();
    $bodies = [
        ['GET', 'api/v1/learning/journey/map', []],
        ['POST', 'api/v1/learning/journey/chest', ['period' => $today]],
        ['POST', 'api/v1/learning/cheers', ['kind' => 'clap']],
        ['POST', 'api/v1/games/plays', ['game' => $this->game, 'station' => $this->ids['node'] ?? null, 'homework_id' => $this->ids['homework'] ?? null]],
        ['POST', 'api/v1/games/hatch', []],
        ['PUT', 'api/v1/games/limits', ['enabled' => true, 'daily_minutes' => 30]],
        ['POST', 'api/v1/messages/conversations', ['with_id' => $this->ids['teacher']]],
        ['POST', 'api/v1/messages/conversations', ['with_id' => $this->ids['student']]],
        ['POST', 'api/v1/safeguarding/concerns', ['category' => 'other', 'text' => 'checking', 'session_id' => $this->ids['session']]],
        ['POST', 'api/v1/support/tickets', ['kind' => 'other', 'subject' => 'checking', 'body' => 'checking', 'session_id' => $this->ids['session']]],
        ['POST', 'api/v1/me/data-requests', ['kind' => 'export', 'subject_id' => $this->ids['student']]],
        ['POST', 'api/v1/me/data-requests', ['kind' => 'erasure', 'subject_id' => $this->ids['student']]],
        ['POST', 'api/v1/me/data-requests', ['kind' => 'correction', 'subject_id' => $this->ids['student'], 'reason' => 'Please correct my birth date.']],
    ];
    foreach ($this->people as $role => $person) {
        $this->actAsTenant($this->noor);
        $token = $person->createToken('sweep-bodies')->plainTextToken;
        $this->clearTenant();
        foreach ($bodies as $i => [$method, $uri, $body]) {
            app('auth')->forgetGuards();
            $body = array_filter($body, fn ($v) => $v !== null) + ['learner_id' => $this->ids['student']];
            $response = $method === 'GET'
                ? $this->withToken($token)->getJson('http://noor.lms.test/'.$uri.'?'.http_build_query($body))
                : $this->withToken($token)->withHeaders(['Idempotency-Key' => "sweep-{$role}-{$i}"])->json($method, 'http://noor.lms.test/'.$uri, $body);
            $note($role, $method.' '.$uri.' (body)', $response, false);
        }
    }

    // 2. The panels and file routes, signed in on the other academy's host (the browser's session, no token).
    $this->flushHeaders();
    app('auth')->shouldUse('web');
    $pages = [
        'owner' => ['admin/curricula/{program}/content', 'admin/enrollments/{enrollment}', 'admin/invoices/{invoice}', 'admin/leads/{lead}', 'admin/payroll/{teacher}',
            'admin/shifts/proposals/{proposal}', 'admin/support/{ticket}', 'admin/teachers/{teacher}/performance', 'admin/time-off/{timeOff}',
            'billing/proofs/{proof}/file', 'curriculum/resources/{resource}', 'learning/files/{submission}', 'recordings/{recording}/media', 'support/files/{message}',
            'class/{session}', 'class/{session}/go', 'people/checks/{check}/file',
            'admin/people/students/{student}/edit', 'admin/people/teachers/{teacher}/edit', 'admin/people/guardians/{user}/edit'],
        'accountant' => ['admin/invoices/{invoice}', 'admin/payroll/{teacher}', 'billing/proofs/{proof}/file'],
        'teacher' => ['teach/reports/{report}', 'teach/reports/{report}/game', 'teach/students/{enrollment}', 'class/{session}', 'class/{session}/go', 'learning/files/{submission}',
            'support/files/{message}', 'people/checks/{check}/file'],
        'guardian' => ['my', 'class/{session}', 'learning/files/{submission}', 'support/files/{message}', 'billing/proofs/{proof}/file'],
        'student' => ['class/{session}', 'class/{session}/go', 'learning/files/{submission}'],
    ];
    foreach ($pages as $role => $paths) {
        foreach ($paths as $path) {
            preg_match_all('/\{(\w+)\}/', $path, $m);
            if (array_diff($m[1], array_keys($this->ids)) !== []) {
                continue;
            }
            $url = preg_replace_callback('/\{(\w+)\}/', fn ($x) => $this->ids[$x[1]], $path);
            app('auth')->forgetGuards();
            $this->flushSession();
            $response = $this->actingAs($this->people[$role], 'web')->get('http://noor.lms.test/'.$url.($path === 'my' ? '?learner='.$this->ids['student'] : ''));
            $note($role, 'GET /'.$url, $response, $m[1] !== []);
        }
    }
    foreach (['class/{session}/ticket', 'class/{session}/recording', 'class/{session}/recording/stop'] as $path) {
        app('auth')->forgetGuards();
        $this->flushSession();
        $response = $this->actingAs($this->people['teacher'], 'web')->postJson('http://noor.lms.test/'.str_replace('{session}', $this->ids['session'], $path));
        $note('teacher', 'POST /'.$path, $response, true);
    }

    // 3. The demo's signed links (signed for any host), opened on the other academy's host by nobody signed in.
    app('auth')->forgetGuards();
    $this->flushSession();
    $signed = [
        ['billing.pay', ['invoice' => $this->ids['invoice']]],
        ['billing.pay.document', ['invoice' => $this->ids['invoice']]],
        ['people.consent', ['student' => $this->ids['student'], 'g' => $this->ids['user']]],
        ['evaluation.rate', ['session' => $this->ids['session'], 'u' => $this->ids['user']]],
        ['identity.welcome', ['user' => $this->ids['user']]],
        ['learning.material', ['resource' => $this->ids['resource'] ?? $this->ids['session']]],
        ['billing.pay.return', ['checkout' => $this->ids['checkout'] ?? $this->ids['invoice']]],
        ['privacy.copy', ['request' => $this->ids['request'] ?? $this->ids['invoice']]],
    ];
    foreach ($signed as [$name, $params]) {
        $url = URL::temporarySignedRoute($name, now()->addHour(), $params, absolute: false);
        $response = $this->get('http://noor.lms.test'.$url);
        $note('anyone', 'GET '.$name.' (signed)', $response, true);
        if (in_array($name, ['people.consent', 'evaluation.rate'], true)) {
            $response = $this->post('http://noor.lms.test'.$url, sweepBody());
            $note('anyone', 'POST '.$name.' (signed)', $response, true);
        }
    }

    // 4. The demo's live channels.
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => [
        'driver' => 'reverb', 'key' => 'k', 'secret' => 's', 'app_id' => 'a', 'options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
    ]]);
    foreach (Broadcast::driver('null')->getChannels() as $pattern => $callback) {
        Broadcast::driver('reverb')->channel($pattern, $callback);
    }
    foreach (array_keys(Broadcast::driver('null')->getChannels()->all()) as $pattern) {
        $channel = preg_replace(['/\{academy\w*\}/i', '/\{(user|person|student|teacher)\w*\}/i', '/\{session\w*\}/i', '/\{\w+\}/'],
            [$this->demo, $this->ids['user'], $this->ids['session'], $this->ids['session']], (string) $pattern);
        foreach (['owner', 'teacher', 'guardian'] as $role) {
            app('auth')->forgetGuards();
            $this->flushSession();
            $response = $this->actingAs($this->people[$role], 'web')->post('http://noor.lms.test/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-'.$channel]);
            $note($role, 'channel '.$channel, $response, true);
        }
    }

    $problems = array_values(array_filter($answers, fn (array $a) => $a[3] !== null));
    if (getenv('SWEEP_DUMP')) {
        foreach ($answers as [$who, $what, $status, $problem, $location, $body]) {
            fwrite(STDERR, sprintf("%-10s %-4s %-70s %s %s %s\n", $who, $status, $what, $problem ?? '', $location, $status === 401 || $status === 422 || $status >= 500 ? str_replace("\n", ' ', $body) : ''));
        }
    }

    expect(count($answers))->toBeGreaterThan(400)
        ->and(array_map(fn (array $a) => "{$a[0]}: {$a[1]} → {$a[3]}", $problems))->toBe([]);
});

it('keeps every stored file under its own academy\'s folder', function () {
    $platform = DB::connection('pgsql_platform');
    $files = ['manual_payment_proofs' => 'file_path', 'recordings' => 'path', 'node_resources' => 'file_path', 'homework_submissions' => 'file_path', 'ticket_messages' => 'file_path'];

    $count = 0;
    foreach ($files as $table => $column) {
        $stray = $platform->table($table)->whereNotNull($column)
            ->whereRaw("{$column} not like 'academies/' || academy_id::text || '/%'")
            ->pluck($column)->all();
        $count += $platform->table($table)->whereNotNull($column)->count();

        expect($stray)->toBe([], "files of {$table} outside their academy's folder");
    }
    expect($count)->toBeGreaterThan(3);   // the demo's proofs, recordings, materials and homework
});
