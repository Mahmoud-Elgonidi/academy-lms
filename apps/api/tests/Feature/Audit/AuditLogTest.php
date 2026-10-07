<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\ChangePassword;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Scheduling\Actions\Policy\SavePolicySettings;
use Modules\Scheduling\Domain\Policy\PolicySettings;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditEntries;
use Modules\SharedKernel\Audit\AuditTrail;

/*
 * The audit log (docs/architecture/03: "audit_logs (ممنوع عليه UPDATE
 * وDELETE)"): what an academy did, by whom, never changed, never shown to
 * another academy, never holding a secret.
 */

beforeEach(function () {
    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->owner = User::factory()->withRole(Role::Owner, Role::Admin)->create(['name' => 'هالة']);
});

afterEach(fn () => AuditActor::forget());

it('keeps who did what, as the signed-in person', function () {
    $this->actingAs($this->owner);
    app(AuditTrail::class)->record('billing.invoice_voided', 'invoice', 'inv-1', ['number' => 'NR-1', 'reason' => 'خطأ']);

    $row = DB::table('audit_logs')->sole();
    expect($row->academy_id)->toBe($this->noor->id)
        ->and($row->actor_type)->toBe('user')
        ->and($row->actor_id)->toBe($this->owner->id)
        ->and($row->actor_name)->toBe('هالة')
        ->and($row->action)->toBe('billing.invoice_voided')
        ->and(json_decode($row->data, true))->toBe(['number' => 'NR-1', 'reason' => 'خطأ']);
});

it('records the system when nobody is signed in, and the platform team on someone\'s behalf when told so', function () {
    app(AuditTrail::class)->record('payroll.month_closed');
    AuditActor::use(new AuditActor('platform', '0190f0a0-0000-7000-8000-000000000001', 'سلمى', $this->owner->id, '0190f0a0-0000-7000-8000-000000000002'));
    app(AuditTrail::class)->record('identity.password_changed');

    $rows = DB::table('audit_logs')->orderBy('created_at')->orderBy('id')->get();
    expect($rows->pluck('actor_type')->sort()->values()->all())->toBe(['platform', 'system'])
        ->and($rows->firstWhere('actor_type', 'platform')->on_behalf_of)->toBe($this->owner->id);
});

it('never stores a secret, a key, a password or a token', function () {
    app(AuditTrail::class)->record('billing.gateway_saved', 'gateway_account', 'g1', [
        'label' => 'Stripe', 'secret_key' => 'sk_live_x', 'api_key' => 'k', 'password' => 'p',
        'fields' => ['publishable' => 'pk', 'webhook_secret' => 'whsec', 'token' => 't'],
    ]);

    expect(json_decode(DB::table('audit_logs')->value('data'), true))->toBe(['label' => 'Stripe', 'fields' => ['publishable' => 'pk']]);
});

it('cannot be changed or removed, even through the platform connection', function () {
    app(AuditTrail::class)->record('policy.saved', data: ['version' => 2]);

    expect(fn () => DB::table('audit_logs')->update(['action' => 'nothing']))->toThrow(QueryException::class, 'append-only')
        ->and(fn () => DB::connection('pgsql_platform')->table('audit_logs')->delete())->toThrow(QueryException::class, 'append-only')
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->count())->toBe(1);
});

it('shows an academy its own entries only, and never the platform\'s', function () {
    app(AuditTrail::class)->record('policy.saved', data: ['version' => 2]);
    $huda = $this->actAsTenant($this->createAcademy(['slug' => 'huda']));
    app(AuditTrail::class)->record('import.done', data: ['file' => 'x.csv']);
    DB::connection('pgsql_platform')->table('audit_logs')->insert(AuditTrail::row(null, new AuditActor('platform', null, 'سلمى'), 'platform.signed_in', null, null, []));

    expect(DB::table('audit_logs')->pluck('action')->all())->toBe(['import.done']);
    $this->actAsTenant($this->noor);
    expect(DB::table('audit_logs')->pluck('action')->all())->toBe(['policy.saved']);
    $this->clearTenant();
    expect(DB::table('audit_logs')->count())->toBe(0)
        ->and(DB::connection('pgsql_platform')->table('audit_logs')->count())->toBe(3);
    $this->actAsTenant($this->noor);
    expect(fn () => DB::table('audit_logs')->insert(AuditTrail::row($huda->id, AuditActor::current(), 'policy.saved', null, null, [])))
        ->toThrow(QueryException::class);
});

it('writes sensitive actions from the modules, such as a changed password', function () {
    $this->actingAs($this->owner);
    app(ChangePassword::class)->handle($this->owner, 'a-new-password-1');

    expect(DB::table('audit_logs')->where('action', 'identity.password_changed')->value('subject_id'))->toBe($this->owner->id);
});

it('refuses a password change while the platform team is signed in as the person', function () {
    AuditActor::use(new AuditActor('platform', '0190f0a0-0000-7000-8000-000000000001', 'سلمى', $this->owner->id));

    expect(fn () => app(ChangePassword::class)->handle($this->owner, 'a-new-password-1'))->toThrow(ValidationException::class);
});

it('reads each entry as a sentence in the reader\'s language and clock', function () {
    app()->setLocale('ar');
    $row = (object) [
        'action' => 'payroll.adjusted', 'data' => json_encode(['kind' => 'bonus', 'amount' => 'SAR 100', 'note' => 'التزام']),
        'actor_type' => 'platform', 'actor_name' => 'سلمى', 'on_behalf_of' => 'u1', 'created_at' => '2026-10-05 09:30:00+00',
    ];

    expect(AuditEntries::describe($row, 'Africa/Cairo', ['u1' => 'هالة']))->toMatchArray([
        'title' => 'مكافأة SAR 100: التزام',
        'actor' => 'فريق المنصة · سلمى، بحساب هالة',
        'platform' => true,
        'group' => 'payroll',
    ])->and(AuditEntries::describe($row, 'Africa/Cairo')['when'])->toContain('12:30');
});

it('records a new policy version with what changed in it', function () {
    $this->actingAs($this->owner);
    $save = app(SavePolicySettings::class);
    $save->handle(PolicySettings::defaults()->toArray(), $this->owner->id);
    $save->handle(['student_notice_hours' => 24] + PolicySettings::defaults()->toArray(), $this->owner->id);

    $last = DB::table('audit_logs')->where('action', 'policy.saved')->orderByDesc('created_at')->get()->firstWhere(fn ($r) => json_decode($r->data, true)['version'] === 2);
    expect(json_decode($last->data, true)['changed'])->toBe(['student_notice_hours']);
});
