<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Announcements\Actions\SendAnnouncement;
use Modules\Announcements\Domain\Audience;
use Modules\Announcements\Filament\Pages\Announcements;
use Modules\Announcements\Models\Announcement;
use Modules\Announcements\Privacy\AnnouncementsData;
use Modules\Announcements\Queries\AudienceMembers;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Communication\Actions\RegisterPushDevice;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\Communication\Filament\NotificationBell;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditEntries;
use Modules\SharedKernel\Privacy\PersonalData;

/*
 * The academy's announcements (docs/architecture/02, section 20). The
 * fixture, Wednesday 14 October 2026 in Cairo: the owner and an admin; a
 * teacher and a teacher whose account is off; Sara (Arabic) with her adult
 * son Omar (English, signs in himself), her daughter Lina (16, her own
 * account, consent given) and little Yousef (7, managed under her, consent
 * given); Tom with Adam (12, his own account, still waiting for consent);
 * Huda with Ziad (an adult whose Quran course ended); a guardian whose
 * account is off and an erased student. Omar learns Quran (running), Lina
 * too (paused), Adam too (running); Yousef learns maths.
 */

beforeEach(function () {
    $this->withoutDefer();
    Mail::fake();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 08:00', 'UTC'));
    $this->noor = $this->actAsTenant($this->createAcademy(['slug' => 'noor', 'default_timezone' => 'Africa/Cairo']));
    $person = fn (array $roles, array $attributes = []) => User::factory()->withRole(...$roles)->create($attributes + ['locale' => 'ar']);
    $people = app(PeopleApi::class);

    $this->owner = $person([Role::Owner], ['name' => 'Hala']);
    $this->admin = $person([Role::Admin], ['name' => 'Amr', 'locale' => 'en']);
    $this->teacher = $person([Role::Teacher], ['name' => 'Nour']);
    $this->offTeacher = $person([Role::Teacher])->forceFill(['status' => 'disabled']);
    $this->offTeacher->save();

    $this->sara = $person([Role::Guardian], ['name' => 'Sara', 'locale' => 'ar']);
    $this->omar = $person([Role::Student], ['name' => 'Omar', 'locale' => 'en']);
    $this->lina = $person([Role::Student], ['name' => 'Lina']);
    $this->yousef = $person([Role::Student], ['name' => 'Yousef', 'is_managed_minor' => true]);
    $this->tom = $person([Role::Guardian], ['name' => 'Tom', 'locale' => 'en']);
    $this->adam = $person([Role::Student], ['name' => 'Adam']);
    $this->huda = $person([Role::Guardian], ['name' => 'Huda']);
    $this->ziad = $person([Role::Student], ['name' => 'Ziad']);
    $this->offGuardian = $person([Role::Guardian], ['status' => 'disabled']);
    $this->erased = $person([Role::Student], ['status' => 'erased']);

    foreach ([[$this->omar, '2000-01-01'], [$this->lina, '2010-06-01'], [$this->yousef, '2019-03-01'], [$this->adam, '2014-02-01'], [$this->ziad, '1998-01-01']] as [$s, $born]) {
        $people->saveStudentProfile($s->id, CarbonImmutable::parse($born), null);
    }
    foreach ([[$this->sara, $this->omar], [$this->sara, $this->lina], [$this->sara, $this->yousef], [$this->tom, $this->adam], [$this->huda, $this->ziad]] as [$g, $s]) {
        $people->linkGuardian($g->id, $s->id);
    }
    app(GrantConsent::class)->handle($this->lina->id, $this->sara->id, 'family');
    app(GrantConsent::class)->handle($this->yousef->id, $this->sara->id, 'family');

    $this->quran = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
    $this->maths = app(CatalogApi::class)->createSubject('رياضيات', 'Maths');
    foreach ([[$this->omar, $this->quran, 'active'], [$this->lina, $this->quran, 'paused'], [$this->adam, $this->quran, 'active'], [$this->ziad, $this->quran, 'ended'], [$this->yousef, $this->maths, 'active']] as [$s, $subject, $status]) {
        DB::table('enrollments')->insert(['id' => (string) Str::uuid7(), 'student_id' => $s->id, 'subject_id' => $subject, 'teacher_id' => $this->teacher->id,
            'status' => $status, 'start_date' => '2026-09-01', 'ended_at' => $status === 'ended' ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
    }
    app()->setLocale('en');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    AuditActor::forget();
});

/** @return list<string> names, sorted */
function reached(Audience $audience): array
{
    $ids = app(AudienceMembers::class)->resolve($audience);
    $names = User::query()->whereIn('id', $ids)->pluck('name')->sort()->values()->all();
    expect(app(AudienceMembers::class)->count($audience))->toBe(count($ids));

    return $names;
}

function announce(User $by, Audience $audience, array $channels = [], string $title = 'Eid holiday', string $body = "Closed Tuesday to Thursday.\nLessons are back on Friday."): Announcement
{
    test()->actingAs($by);

    return app(SendAnnouncement::class)->handle($by->id, $title, $body, $audience, $channels);
}

function announcementRefusal(Closure $send): ?string
{
    try {
        $send();
    } catch (ValidationException $e) {
        return collect($e->errors())->flatten()->first();
    }

    return null;
}

it('reaches each audience as chosen: active accounts, children under a guardian only as students, never a child waiting for consent', function () {
    // Every family: the guardians and the students who sign in themselves (not Yousef, managed; not Adam, waiting).
    expect(reached(Audience::of('families')))->toBe(['Huda', 'Lina', 'Omar', 'Sara', 'Tom', 'Ziad'])
        ->and(reached(Audience::of('guardians')))->toBe(['Huda', 'Sara', 'Tom'])
        // Students outright: Yousef too, still never Adam or an erased account.
        ->and(reached(Audience::of('students')))->toBe(['Lina', 'Omar', 'Yousef', 'Ziad'])
        ->and(reached(Audience::of('students', 'kids')))->toBe(['Yousef'])
        ->and(reached(Audience::of('students', 'teen')))->toBe(['Lina'])
        ->and(reached(Audience::of('students', 'adult')))->toBe(['Omar', 'Ziad'])
        ->and(reached(Audience::of('students', 'middle')))->toBe([])
        // A subject's families: running and paused courses, not ended ones; Adam's father hears, Adam does not yet.
        ->and(reached(Audience::of('subject', null, $this->quran)))->toBe(['Lina', 'Omar', 'Sara', 'Tom'])
        // Yousef's maths reaches his mother only.
        ->and(reached(Audience::of('subject', null, $this->maths)))->toBe(['Sara'])
        ->and(reached(Audience::of('teachers')))->toBe(['Nour']);

    // Once Adam's father agrees, Adam is reached too.
    app(GrantConsent::class)->handle($this->adam->id, $this->tom->id, 'family');
    expect(reached(Audience::of('students', 'middle')))->toBe(['Adam'])
        ->and(reached(Audience::of('families')))->toContain('Adam');
});

it('reaches everyone in their own language in the notifications they have, after the commit, on the channels chosen', function () {
    app(RegisterPushDevice::class)->handle($this->sara->id, 'android', 'token-sara', '1.0.0');

    $sent = DB::transaction(function () {
        $a = announce($this->owner, Audience::of('subject', null, $this->quran), ['push'], 'Eid holiday', "Closed Tuesday to Thursday.\n\nLessons are back on Friday.");
        expect(Notification::query()->where('template_key', 'like', 'announcements.%')->count())->toBe(0);   // nothing before the commit

        return $a;
    });

    expect($sent->recipients_count)->toBe(4)
        ->and($sent->channels)->toBe(['push'])
        ->and($sent->sent_by)->toBe($this->owner->id);
    $inbox = fn (User $u) => Notification::query()->where('user_id', $u->id)->where('template_key', 'announcements.families')->sole();
    $sara = $inbox($this->sara);
    expect($sara->title)->toBe('إعلان: Eid holiday')
        ->and($sara->body)->toBe("Closed Tuesday to Thursday.\n\nLessons are back on Friday.")
        ->and($sara->category)->toBe('lessons')
        ->and($sara->action)->toEqual(['screen' => 'announcement', 'id' => $sent->id])
        // A phone notification only: email was not chosen, WhatsApp never.
        ->and($sara->deliveries()->pluck('channel')->all())->toBe(['push'])
        ->and($inbox($this->omar)->title)->toBe('Announcement: Eid holiday')
        ->and(Notification::query()->where('template_key', 'like', 'announcements.%')->whereIn('user_id', [$this->ziad->id, $this->huda->id, $this->adam->id, $this->yousef->id])->count())->toBe(0);

    // The apps read it from the inbox as written, in each person's language.
    $token = $this->omar->createToken('phone')->plainTextToken;
    app('auth')->forgetGuards();
    $this->clearTenant();
    $this->withToken($token)->getJson('http://noor.lms.test/api/v1/notifications')->assertOk()
        ->assertJsonPath('data.0.title', 'Announcement: Eid holiday')
        ->assertJsonPath('data.0.body', "Closed Tuesday to Thursday.\n\nLessons are back on Friday.")
        ->assertJsonPath('data.0.action.screen', 'announcement')
        ->assertJsonPath('data.0.action.id', $sent->id);
    $this->actAsTenant($this->noor);

    // Teachers hear it under work, by email only when that is the choice.
    $toTeachers = announce($this->admin, Audience::of('teachers'), ['email'], 'Report questions', 'Two new questions at the end of the report.');
    $nour = Notification::query()->where('user_id', $this->teacher->id)->where('template_key', 'announcements.teachers')->sole();
    expect($toTeachers->recipients_count)->toBe(1)
        ->and($nour->category)->toBe('staff')
        ->and($nour->title)->toBe('إعلان: Report questions')
        ->and($nour->deliveries()->pluck('channel')->all())->toBe(['email']);
});

it('sends at most five a day, the day being the academy\'s', function () {
    // 22:00 in Cairo (19:00 UTC): five today.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 19:00', 'UTC'));
    for ($i = 1; $i <= 5; $i++) {
        announce($this->owner, Audience::of('guardians'), [], "Notice {$i}");
    }
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 20:30', 'UTC'));   // 23:30 in Cairo, the same day there
    expect(announcementRefusal(fn () => announce($this->admin, Audience::of('teachers'))))->toBe(__('announcements::announcements.errors.daily_cap', ['n' => 5]));

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 21:05', 'UTC'));   // 00:05 in Cairo: a new day there, not yet in UTC
    expect(announce($this->admin, Audience::of('teachers'))->recipients_count)->toBe(1)
        ->and(Announcement::query()->count())->toBe(6);
});

it('audits the audience and the count, never the words', function () {
    $a = announce($this->owner, Audience::of('students', 'teen'), ['push', 'email'], 'Exam week', 'Bring your notebook on Sunday.');

    $row = DB::table('audit_logs')->where('action', 'announcements.sent')->sole();
    expect(json_decode($row->data, true))->toEqual(['audience' => 'students', 'count' => 1])
        ->and($row->subject_id)->toBe($a->id)
        ->and($row->actor_id)->toBe($this->owner->id)
        ->and($row->data)->not->toContain('Exam')->not->toContain('notebook')
        // Read as a sentence on the activity log, in the reader's language.
        ->and(AuditEntries::describe($row, 'Africa/Cairo')['title'])->toBe('Sent an announcement to students (recipients: 1)')
        ->and(AuditEntries::describe($row, 'Africa/Cairo')['group'])->toBe('announcements');
});

it('refuses what cannot be sent, and who may not send', function () {
    $refused = fn (Closure $send) => announcementRefusal($send);

    expect($refused(fn () => announce($this->owner, Audience::of('students', 'middle'))))->toBe(__('announcements::announcements.errors.nobody'))
        ->and($refused(fn () => announce($this->owner, Audience::of('guardians'), ['whatsapp'])))->toBe(__('announcements::announcements.errors.channels_channel'))
        ->and($refused(fn () => announce($this->owner, Audience::of('guardians'), [], '   ')))->toBe(__('announcements::announcements.errors.title_required'))
        ->and($refused(fn () => announce($this->owner, Audience::of('guardians'), [], 'Eid', str_repeat('a', 1001))))->toBe(__('announcements::announcements.errors.body_too_long', ['max' => 1000]))
        ->and($refused(fn () => announce($this->owner, Audience::of('subject', null, (string) Str::uuid7()))))->toBe(__('announcements::announcements.errors.subject'));

    foreach ([Role::Supervisor, Role::Scheduler, Role::Accountant, Role::Sales, Role::Teacher, Role::Guardian] as $role) {
        $someone = User::factory()->withRole($role)->create();
        expect(fn () => announce($someone, Audience::of('guardians')))->toThrow(AuthorizationException::class);
    }

    // The platform team signed in as the owner does not speak for the academy.
    AuditActor::use(new AuditActor('platform', 'pa-1', 'Platform', $this->owner->id));
    expect($refused(fn () => announce($this->owner, Audience::of('guardians'))))->toBe(__('announcements::announcements.errors.impersonating'));
    AuditActor::forget();

    expect(Announcement::query()->count())->toBe(0)
        ->and(DB::table('audit_logs')->where('action', 'announcements.sent')->count())->toBe(0);
});

it('keeps a sent announcement exactly as it was sent', function () {
    $a = announce($this->owner, Audience::of('guardians'));

    expect(fn () => $a->update(['title' => 'Changed']))->toThrow(LogicException::class)
        ->and(fn () => $a->delete())->toThrow(LogicException::class)
        ->and(fn () => DB::table('announcements')->where('id', $a->id)->update(['title' => 'Changed']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('announcements')->where('id', $a->id)->delete())->toThrow(QueryException::class)
        ->and(Announcement::query()->find($a->id)->title)->toBe('Eid holiday');
});

it('opens the screen to the owner and admins only, and an announcement of its own academy only', function () {
    $a = announce($this->owner, Audience::of('guardians'), [], 'Ramadan timetable');
    Filament::setCurrentPanel('academy');
    foreach ([$this->owner, $this->admin] as $who) {
        $this->actAsTenant($this->noor);   // a request ends the one before
        $this->flushSession();
        $this->actingAs($who)->get('http://noor.lms.test/admin/announcements')->assertOk()->assertSee('Ramadan timetable')->assertSee('Hala');
        $this->get('http://noor.lms.test/admin/announcements?announcement='.$a->id)->assertOk()->assertSee('Ramadan timetable');
        $this->get('http://noor.lms.test/admin/announcements?announcement='.Str::uuid7())->assertNotFound();
        $this->get('http://noor.lms.test/admin/announcements?announcement=not-an-id')->assertNotFound();
    }
    foreach ([Role::Supervisor, Role::Scheduler, Role::Accountant, Role::Sales, Role::Teacher] as $role) {
        $this->actAsTenant($this->noor);
        $this->flushSession();
        $this->actingAs(User::factory()->withRole($role)->create());
        $this->get('http://noor.lms.test/admin/announcements')->assertForbidden();
    }

    // Another academy's owner sees none of it, and cannot open it.
    $this->actAsTenant($rawda = $this->createAcademy(['slug' => 'rawda']));
    $other = User::factory()->withRole(Role::Owner)->create();
    expect(app(AudienceMembers::class)->resolve(Audience::of('guardians')))->toBe([]);
    $this->flushSession();
    $this->actingAs($other)->get('http://rawda.lms.test/admin/announcements')->assertOk()->assertDontSee('Ramadan timetable');
    $this->actAsTenant($rawda);
    $this->get('http://rawda.lms.test/admin/announcements?announcement='.$a->id)->assertNotFound();
});

it('writes, counts, asks and sends from the screen, then lists what was sent', function () {
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->admin);

    $page = Livewire::test(Announcements::class)
        ->assertSeeHtml('data-count="6"')                       // every family, at first
        ->fillForm(['audience' => 'students', 'stage' => 'kids'])
        ->assertSeeHtml('data-count="1"')
        ->fillForm(['audience' => 'subject', 'subject_id' => $this->quran])
        ->assertSeeHtml('data-count="4"');

    // Nothing asked before the words are there.
    $page->callAction('send')->assertHasFormErrors(['title' => 'required', 'body' => 'required']);
    expect(Announcement::query()->count())->toBe(0);

    $page->fillForm(['title' => 'Quran exam', 'body' => "On Sunday.\nBring your mushaf.", 'channels' => ['email']])
        ->callAction('send')
        ->assertHasNoFormErrors()
        ->assertNotified(__('announcements::announcements.send.sent', ['people' => trans_choice('announcements::announcements.reach.people', 4, ['n' => 4])]))
        ->assertFormSet(['title' => '', 'body' => '', 'audience' => 'families']);

    $a = Announcement::query()->sole();
    expect([$a->audience, $a->subject_id, $a->channels, $a->recipients_count, $a->sent_by])->toBe(['subject', $this->quran, ['email'], 4, $this->admin->id]);
    $page->assertSee('Quran exam')->assertSee('Amr')->assertSee('4 announcements left today');
});

it('opens an announcement in place in the bell, and on the announcements screen for who sends them', function () {
    $a = announce($this->owner, Audience::of('families'), [], 'Eid holiday', "Closed Tuesday to Thursday.\nLessons are back on Friday.");
    $n = Notification::query()->where('user_id', $this->sara->id)->where('template_key', 'announcements.families')->sole();

    $this->actingAs($this->sara);
    Filament::setCurrentPanel('family');
    Livewire::test(NotificationBell::class)
        ->call('open', $n->id)
        ->assertNoRedirect()
        ->assertSet('expanded', $n->id)
        ->assertSeeHtml('data-open');
    expect($n->refresh()->read_at)->not->toBeNull();

    $links = app(NotificationLinks::class);
    expect($links->resolve($n->action, 'family'))->toBeNull()
        ->and($links->resolve($n->action, 'teach'))->toBeNull();
    $this->actingAs($this->owner);
    expect($links->resolve($n->action, 'academy'))->toBe(route('filament.academy.pages.announcements', ['announcement' => $a->id]));
    $this->actingAs(User::factory()->withRole(Role::Supervisor)->create());
    expect($links->resolve($n->action, 'academy'))->toBeNull();
});

it('gives the sender a copy of what they sent, keeps it on erasure and holds nothing back', function () {
    announce($this->owner, Audience::of('guardians'), ['push'], 'Eid holiday', 'Closed on Tuesday.');
    $source = collect(app(PersonalData::class)->sources())->first(fn ($s) => $s instanceof AnnouncementsData);

    $copy = $source->export($this->owner->id);
    expect($copy->data['sent'])->toHaveCount(1)
        ->and($copy->data['sent'][0])->toMatchArray(['title' => 'Eid holiday', 'body' => 'Closed on Tuesday.', 'audience' => 'guardians', 'channels' => ['inbox', 'push'], 'recipients' => 3])
        ->and($source->export($this->sara->id)->isEmpty())->toBeTrue()
        ->and($source->blockers($this->owner->id))->toBe([])
        ->and($source->erase($this->owner->id))->toBe([])
        ->and(Announcement::query()->count())->toBe(1);
});
