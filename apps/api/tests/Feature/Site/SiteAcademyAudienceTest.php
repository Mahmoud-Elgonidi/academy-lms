<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Models\Academy;
use Modules\SharedKernel\Http\FormToken;
use Modules\Site\Actions\ApplySiteTemplate;
use Modules\Site\Actions\SubmitJoinRequest;
use Modules\Site\Contracts\SiteApi;
use Modules\Site\Domain\Page\Templates;
use Modules\Site\Models\SitePage;
use Modules\Site\Models\SiteSettings;
use Tests\Support\AcademySite;
use Tests\Support\SitePages;

uses(AcademySite::class, SitePages::class);

/*
 * An academy that trains adults only (academies.audience, docs/architecture/02
 * section 29) speaks to adults on its page, whatever a page kept says: its
 * pages read as adults' (the form takes the learner alone, no child's age
 * group), it starts from the page for adults, it never starts from the page
 * for children and families, and its trial messages and refusals say a
 * session. A families academy keeps its page's own choice.
 */

beforeEach(function () {
    $this->withoutDefer();
    Mail::fake();
    $this->siteAcademy();
});

/** Noor becomes an academy that trains adults only (as SetAcademyAudience would). */
function noorTrainsAdults(): void
{
    Academy::query()->whereKey(test()->academy->id)->update(['audience' => 'adults']);
    test()->academy = Academy::query()->findOrFail(test()->academy->id)->toTenantRef();
    test()->actAsTenant(test()->academy);
}

function adultAsk(array $change = []): array
{
    $token = FormToken::issue(SubmitJoinRequest::FORM);
    test()->travel(5)->seconds();

    return array_merge([
        'kind' => 'trial', 'name' => 'Tarek Saleh', 'phone' => '+966501234567', 'timezone' => 'Asia/Riyadh',
        'subject_id' => test()->quran, 'for_child' => true, 'child_name' => 'Yara', 'child_birth_year' => now()->year - 9,
        'form_token' => $token, 'website' => '',
    ], $change);
}

it('reads every page of an adults academy as a page for adults, and leaves a families academy\'s choice', function () {
    $this->actingAs($this->owner);
    // A families page with a form that takes children, published before the academy changed.
    $this->inNoor(fn () => $this->publishPage([['type' => 'form', 'data' => ['who' => 'children']], ['type' => 'teachers', 'data' => ['show_ages' => true]]]));
    $form = fn () => collect($this->getJson('http://noor.lms.test/api/v1/site')->assertOk()->json('data.page.blocks'))->firstWhere('type', 'form');
    expect($form()['data']['who'])->toBe('children')
        ->and($this->getJson('http://noor.lms.test/api/v1/site')->json('data.page.settings.audience'))->toBe('families');

    noorTrainsAdults();
    expect(SitePage::current()->publishedDoc()['settings']['audience'])->toBe('adults')
        ->and(SitePage::current()->draftDoc()['settings']['audience'])->toBe('adults')
        ->and(app(SiteApi::class)->trialSettings()->forAdults)->toBeTrue();
    $this->clearTenant();
    $site = $this->getJson('http://noor.lms.test/api/v1/site')->assertOk()->json('data');
    expect($site['page']['settings']['audience'])->toBe('adults')
        ->and($form()['data']['who'])->toBe('adults');
    // No teacher's card names a child's age group there.
    foreach ($site['teachers'] as $teacher) {
        expect(array_diff($teacher['age_groups'] ?? [], ['adults']))->toBe([]);
    }

    // The visitor books for themself alone, and is told so in the academy's words.
    $this->postJson('http://noor.lms.test/api/v1/site/requests', adultAsk(), ['Accept-Language' => 'ar'])
        ->assertUnprocessable()->assertJsonValidationErrors('for_child');
    $this->postJson('http://noor.lms.test/api/v1/site/requests', adultAsk(['for_child' => false]))->assertCreated();
});

it('starts an adults academy from the page for adults, and never from the page for children and families', function () {
    expect(Templates::keysFor(false))->toBe(Templates::KEYS)
        ->and(Templates::keysFor(true))->not->toContain('kids_families')->toContain('training', 'online_courses', 'blank');
    // A families academy with no page yet reads its old page, as before.
    expect($this->inNoor(fn () => SitePage::current()->template))->toBe('kids_families');

    noorTrainsAdults();
    // An academy that saved words of its own keeps them (as blocks); one that saved none starts from the page for adults.
    expect(SitePage::current()->template)->toBe('kids_families')
        ->and(SitePage::current()->draftDoc()['settings']['audience'])->toBe('adults');
    SiteSettings::query()->update(['content' => '{}']);
    $page = SitePage::current();
    expect($page->exists)->toBeFalse()
        ->and($page->template)->toBe('online_courses')
        ->and($page->draftDoc()['settings']['audience'])->toBe('adults');

    $this->actingAs($this->owner);
    expect(fn () => app(ApplySiteTemplate::class)->handle('kids_families'))->toThrow(ValidationException::class);
    expect(app(ApplySiteTemplate::class)->handle('blank')->draftDoc()['settings']['audience'])->toBe('adults');
});
