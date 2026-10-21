<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Communication\Contracts\NotificationLinks;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Models\TeacherPublicCard;
use Modules\SharedKernel\Audit\AuditEntries;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupChecklist;
use Modules\Site\Actions\HandleJoinRequest;
use Modules\Site\Actions\SaveSiteSettings;
use Modules\Site\Contracts\SiteApi;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Filament\Pages\SiteRequests;
use Modules\Site\Filament\Pages\SiteSettings as SiteSettingsPage;
use Modules\Site\Models\JoinRequest;
use Modules\Site\Models\SiteSettings;
use Tests\Support\AcademySite;

uses(AcademySite::class);

/*
 * Running the academy's page from /admin (docs/architecture/02, section 24):
 * the owner and admins save what it says (cleaned, held to its lengths,
 * audited with the sections only) and take any teacher's card off it; the
 * requests it brings go to whoever answers each kind (sales for a trial,
 * who runs the teachers for a teacher), who answer them with a note. A
 * request leaves after a year.
 */

beforeEach(function () {
    $this->withoutDefer();
    Mail::fake();
    $this->siteAcademy(enabled: false);
    $this->actAsTenant($this->academy);
    Filament::setCurrentPanel('academy');
});

/** A request as the page keeps it. */
function siteRequest(string $kind, array $change = []): JoinRequest
{
    return JoinRequest::query()->create($change + ['kind' => $kind, 'name' => $kind === 'trial' ? 'Marwa Salem' : 'Ahmed Zaki', 'phone' => '+966501234567', 'locale' => 'en']);
}

it('keeps what the owner saves, cleaned and held to its limits, and audits whether it is on and its sections only', function () {
    $this->actingAs($this->owner);
    $content = SiteContent::defaults();
    $content['hero']['title_ar'] = "  أكاديميتك\u{0007} أونلاين  ";
    $content['hero']['title_en'] = '';
    $content['features'] = [['title_ar' => 'حصص فردية', 'title_en' => '', 'text_ar' => '', 'text_en' => 'One to one'], ['title_ar' => '', 'title_en' => '', 'text_ar' => '', 'text_en' => '']];
    $content['sections'] = ['faq', 'nonsense', 'teachers'];
    $content['whatsapp'] = '00966 50 123 4567';

    $kept = app(SaveSiteSettings::class)->handle(['enabled' => true] + $content);

    expect($kept->enabled)->toBeTrue()
        ->and($kept->content['hero']['title_ar'])->toBe('أكاديميتك أونلاين')
        ->and($kept->content['features'])->toBe([['title_ar' => 'حصص فردية', 'title_en' => '', 'text_ar' => '', 'text_en' => 'One to one']])
        ->and($kept->content['sections'])->toBe(['teachers', 'faq'])
        ->and($kept->content['whatsapp'])->toBe('+966501234567')
        ->and(SiteSettings::isOn())->toBeTrue();
    $audit = DB::table('audit_logs')->where('action', 'site.settings_saved')->sole();
    expect(json_decode($audit->data, true))->toEqual(['state' => 'on', 'shown' => ['teachers', 'faq']])
        ->and(AuditEntries::describe($audit, 'UTC')['title'])->toBe(__('shared::audit.actions.site.settings_saved', ['state' => 'on', 'shown' => 'teachers, questions']))
        ->and(AuditEntries::group($audit->action))->toBe('site');

    $bad = fn (array $change) => fn () => app(SaveSiteSettings::class)->handle(array_replace_recursive($content, $change));
    expect($bad(['hero' => ['title_ar' => str_repeat('x', 121)]]))->toThrow(ValidationException::class)
        ->and($bad(['hero' => ['title_ar' => '', 'title_en' => '']]))->toThrow(ValidationException::class)
        ->and($bad(['faqs' => [['q_ar' => 'Why?', 'q_en' => '', 'a_ar' => '', 'a_en' => '']]]))->toThrow(ValidationException::class)
        ->and($bad(['whatsapp' => '0501234567']))->toThrow(ValidationException::class)
        ->and($bad(['features' => array_fill(0, 7, ['title_en' => 'A', 'text_en' => 'B'])]))->toThrow(ValidationException::class);

    // Only the owner and admins.
    foreach ([$this->sales, $this->scheduler, $this->teachers['sara']] as $someone) {
        $this->actingAs($someone);
        expect(fn () => app(SaveSiteSettings::class)->handle($content))->toThrow(AuthorizationException::class);
    }
});

it('opens the settings to the owner and admins, and saves from the screen', function () {
    foreach ([$this->sales, $this->scheduler, $this->accountant] as $someone) {
        $this->actingAs($someone);
        expect(SiteSettingsPage::canAccess())->toBeFalse();
    }
    $this->actingAs($this->admin);
    Livewire::test(SiteSettingsPage::class)
        ->assertSee(__('site::site.settings.title', [], 'en'))
        ->assertSee('Ms Sara')->assertSee('Ms Huda')->assertSee('Ms Mona')
        ->set('data.enabled', true)
        ->set('data.hero.title_en', 'Learn Quran at home')
        ->call('save')
        ->assertNotified(__('site::site.settings.saved', [], 'en'));

    expect(SiteSettings::current()->enabled)->toBeTrue()
        ->and(SiteSettings::current()->content['hero']['title_en'])->toBe('Learn Quran at home')
        ->and(SiteSettingsPage::pageUrl('ar'))->toStartWith('http://noor.lms.test')->toEndWith('/ar');
    $this->get('http://noor.lms.test/admin/settings/site')->assertOk();
});

it('takes a teacher\'s card off the page and puts it back, the teacher\'s own switch untouched', function () {
    $card = TeacherPublicCard::query()->where('teacher_id', $this->teachers['sara']->id)->sole();
    $this->actingAs($this->owner);

    Livewire::test(SiteSettingsPage::class)
        ->callAction(TestAction::make('card')->arguments(['card' => $card->id, 'hide' => true]))
        ->assertNotified(__('site::site.settings.card.hidden_done', [], 'en'));
    expect($card->refresh()->only(['hidden_by_academy', 'shown']))->toBe(['hidden_by_academy' => true, 'shown' => true])
        ->and(json_decode(DB::table('audit_logs')->where('action', 'people.card_hidden')->value('data'), true))->toBe(['teacher' => 'Sara Fathy']);
    $this->clearTenant();
    expect($this->getJson('http://noor.lms.test/api/v1/site')->status())->toBe(404);   // off still: nothing to see either way
    $this->actAsTenant($this->academy);

    Livewire::test(SiteSettingsPage::class)->callAction(TestAction::make('card')->arguments(['card' => $card->id, 'hide' => false]));
    expect($card->refresh()->hidden_by_academy)->toBeFalse();

    // Nobody else takes cards off.
    $this->actingAs($this->sales);
    expect(fn () => app(PeopleApi::class)->hideTeacherCard($card->id, true))->toThrow(AuthorizationException::class);
});

it('lists the requests of the kinds each person answers, and they answer them', function () {
    $trial = siteRequest('trial', ['for_child' => true, 'child_name' => 'Yara', 'utm_source' => 'instagram']);
    $teach = siteRequest('teach', ['subject_ids' => [$this->quran], 'years_teaching' => 6, 'phone' => '+201002223333']);

    // Sales: trials only.
    $this->actingAs($this->sales);
    expect(SiteRequests::canAccess())->toBeTrue()->and(SiteRequests::getNavigationBadge())->toBe('1');
    Livewire::test(SiteRequests::class)
        ->assertSee('Marwa Salem')->assertSee('https://wa.me/966501234567')->assertSee('Yara')->assertDontSee('Ahmed Zaki')
        ->callAction(TestAction::make('contacted')->arguments(['request' => $trial->id]))
        ->assertNotified(__('site::site.requests.answered', [], 'en'));
    expect($trial->refresh()->only(['status', 'handled_by']))->toBe(['status' => 'contacted', 'handled_by' => $this->sales->id])
        ->and(json_decode(DB::table('audit_logs')->where('action', 'site.request_handled')->value('data'), true))->toBe(['request_status' => 'contacted']);
    expect(fn () => app(HandleJoinRequest::class)->handle($teach->id, 'accepted'))->toThrow(AuthorizationException::class);

    // The accountant answers none.
    $this->actingAs($this->accountant);
    expect(SiteRequests::canAccess())->toBeFalse();

    // The scheduler works leads and runs the teachers: both, filtered by kind.
    $this->actingAs($this->scheduler);
    Livewire::withQueryParams(['kind' => 'teach'])->test(SiteRequests::class)
        ->assertSee('Ahmed Zaki')->assertDontSee('Marwa Salem')
        ->callAction(TestAction::make('accept')->arguments(['request' => $teach->id]), ['note' => 'Interview on Sunday']);
    expect($teach->refresh()->only(['status', 'note']))->toBe(['status' => 'accepted', 'note' => 'Interview on Sunday']);
    Livewire::withQueryParams(['status' => 'open', 'kind' => 'all'])->test(SiteRequests::class)->assertSee('Marwa Salem')->assertDontSee('Ahmed Zaki');

    $this->actingAs($this->owner);
    Livewire::test(SiteRequests::class)->callAction(TestAction::make('decline')->arguments(['request' => $trial->id]), ['note' => 'Out of our hours']);
    expect($trial->refresh()->only(['status', 'note']))->toBe(['status' => 'declined', 'note' => 'Out of our hours'])
        ->and(fn () => app(HandleJoinRequest::class)->handle($trial->id, 'new'))->toThrow(ValidationException::class);
});

it('opens the lead the CRM made from a request, for those who work leads', function () {
    $request = siteRequest('trial');
    $leadId = (string) Str::uuid7();
    DB::table('leads')->insert(['id' => $leadId, 'contact_name' => 'Marwa Salem', 'phone_e164' => '+966501234567', 'timezone' => 'Asia/Riyadh', 'source' => 'website', 'created_at' => now(), 'updated_at' => now()]);
    app(SiteApi::class)->linkLead($request->id, $leadId);

    $this->actingAs($this->sales);
    Livewire::test(SiteRequests::class)->assertSee('/admin/leads/'.$leadId)->assertSee(__('site::site.requests.open_lead', [], 'en'));
});

it('opens a request\'s notification on the requests page for whoever answers it', function () {
    $links = app(NotificationLinks::class);
    $this->actingAs($this->sales);
    expect($links->resolve(['screen' => 'site_request', 'id' => 'x'], 'academy'))->toEndWith('/admin/site/requests')
        ->and($links->resolve(['screen' => 'site_request', 'id' => 'x'], 'teach'))->toBeNull();
    $this->actingAs($this->accountant);
    expect($links->resolve(['screen' => 'site_request', 'id' => 'x'], 'academy'))->toBeNull();
});

it('is an optional step of "Start here", done once the page is on', function () {
    $this->actingAs($this->owner);
    $step = collect(app(SetupChecklist::class)->steps())->first(fn ($s) => $s->key() === 'site.page');

    expect($step->required())->toBeFalse()->and($step->done())->toBeFalse()
        ->and($step->url())->toEndWith('/admin/settings/site')
        ->and($step->ability())->toBe(Ability::ManageSite);
    SiteSettings::query()->update(['enabled' => true]);
    expect($step->done())->toBeTrue();
    $this->actingAs($this->sales);
    expect($step->url())->toBeNull()->and(Gate::allows(Ability::ManageSite->value))->toBeFalse();
});

it('lets requests go a year after they came, whatever became of them', function () {
    $old = siteRequest('trial', ['status' => 'accepted']);
    $this->travel(6)->months();
    $recent = siteRequest('teach');
    $this->travel(7)->months();
    $this->clearTenant();

    $this->artisan('site:sweep')->assertSuccessful();
    expect($this->inNoor(fn () => JoinRequest::query()->pluck('id')->all()))->toBe([$recent->id]);
});
