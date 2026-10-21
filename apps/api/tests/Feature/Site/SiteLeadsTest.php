<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Crm\Actions\CreateLead;
use Modules\Crm\Enums\LeadSource;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;
use Modules\Platform\Actions\Plans\ChangeAcademyPlan;
use Modules\Platform\Models\SaasPlan;
use Modules\SharedKernel\Http\FormToken;
use Modules\Site\Actions\SubmitJoinRequest;
use Modules\Site\Contracts\SiteApi;
use Modules\Site\Models\JoinRequest;
use Tests\Support\AcademySite;

uses(AcademySite::class);

/*
 * The CRM follows the academy's page (docs/architecture/02, section 24): a
 * free trial asked for there becomes a lead from "website", with its
 * campaign, subject, learner and clock, and the request points at it. A
 * number an open lead already has is that lead asking again, said on its
 * timeline, and nothing of it reaches the visitor. The data keeps flowing
 * when the academy's plan leaves the CRM out.
 */

beforeEach(function () {
    $this->withoutDefer();
    Mail::fake();
    $this->siteAcademy();
});

function trialForm(array $change = []): array
{
    $token = FormToken::issue(SubmitJoinRequest::FORM);
    test()->travel(5)->seconds();

    return array_merge([
        'kind' => 'trial', 'name' => 'Marwa Salem', 'phone' => '+966501234567', 'country' => 'KW', 'subject_id' => test()->quran,
        'for_child' => true, 'child_name' => 'Yara', 'child_birth_year' => now()->year - 9, 'message' => 'Evenings please.',
        'utm_source' => 'instagram', 'utm_medium' => 'paid', 'utm_campaign' => 'ramadan', 'form_token' => $token,
    ], $change);
}

it('makes a lead of a free trial asked for on the page, and the request points at it', function () {
    $this->postJson('http://noor.lms.test/api/v1/site/requests', trialForm(), ['Accept-Language' => 'en'])->assertCreated();

    [$lead, $request] = $this->inNoor(fn () => [Lead::query()->sole(), JoinRequest::query()->sole()]);
    expect($lead->only(['contact_name', 'phone_e164', 'country', 'timezone', 'locale', 'for_child', 'learner_name', 'learner_birth_year', 'subject_id', 'utm_source', 'utm_medium', 'utm_campaign', 'notes', 'owner_id']))->toBe([
        'contact_name' => 'Marwa Salem', 'phone_e164' => '+966501234567', 'country' => 'KW', 'timezone' => 'Asia/Kuwait', 'locale' => 'en', 'for_child' => true,
        'learner_name' => 'Yara', 'learner_birth_year' => now()->year - 9, 'subject_id' => $this->quran, 'utm_source' => 'instagram', 'utm_medium' => 'paid',
        'utm_campaign' => 'ramadan', 'notes' => 'Evenings please.', 'owner_id' => null,
    ])->and($lead->source)->toBe(LeadSource::Website)
        ->and($request->lead_id)->toBe($lead->id)
        ->and($this->inNoor(fn () => app(SiteApi::class)->joinRequest($request->id)?->leadId))->toBe($lead->id);
});

it('takes the clock the visitor\'s browser gave, else their country\'s, else the academy\'s', function () {
    $this->postJson('http://noor.lms.test/api/v1/site/requests', trialForm(['timezone' => 'Europe/London', 'country' => 'GB', 'phone' => '+447700900001']))->assertCreated();
    $this->postJson('http://noor.lms.test/api/v1/site/requests', trialForm(['country' => 'XK', 'phone' => '+38349000001', 'for_child' => false]))->assertCreated();

    $leads = $this->inNoor(fn () => Lead::query()->pluck('timezone', 'phone_e164')->all());
    $unknown = $this->inNoor(fn () => Lead::query()->where('phone_e164', '+38349000001')->sole());
    expect($leads)->toBe(['+447700900001' => 'Europe/London', '+38349000001' => 'Africa/Cairo'])
        ->and($unknown->country)->toBeNull()->and($unknown->for_child)->toBeFalse()->and($unknown->learner_name)->toBeNull();
});

it('puts a number an open lead already has on that lead\'s timeline, and never says whose it is', function () {
    $open = $this->inNoor(fn () => app(CreateLead::class)->handle([
        'contact_name' => 'Secret Name', 'phone_e164' => '+966501234567', 'timezone' => 'Asia/Riyadh', 'source' => 'whatsapp',
    ], $this->sales->id));

    $answer = $this->postJson('http://noor.lms.test/api/v1/site/requests', trialForm(['message' => 'Still waiting for a call']))->assertCreated();

    expect((string) $answer->getContent())->not->toContain('Secret Name');
    [$leads, $request, $activity] = $this->inNoor(fn () => [Lead::query()->count(), JoinRequest::query()->sole(), LeadActivity::query()->where('kind', 'website_again')->sole()]);
    expect($leads)->toBe(1)
        ->and($request->lead_id)->toBe($open->id)
        ->and($activity->lead_id)->toBe($open->id)
        ->and($activity->body)->toBe('Still waiting for a call')
        ->and($activity->data)->toBe(['request' => $request->id])
        ->and(__('crm::leads.activity.website_again'))->not->toBe('crm::leads.activity.website_again');
});

it('keeps the leads coming while the academy\'s plan leaves the CRM out, and tells its owner and admins', function () {
    $plan = SaasPlan::query()->create(['code' => 'basic', 'name_ar' => 'أساسية', 'name_en' => 'Basic', 'features' => []]);
    app(ChangeAcademyPlan::class)->handle($this->academy->id, $plan->id, 'For this test');

    $this->postJson('http://noor.lms.test/api/v1/site/requests', trialForm())->assertCreated();

    expect($this->inNoor(fn () => Lead::query()->count()))->toBe(1)
        ->and($this->inNoor(fn () => DB::table('notifications')->where('template_key', 'site.request_new')->pluck('user_id')->sort()->values()->all()))
        ->toBe(collect([$this->owner, $this->admin])->pluck('id')->sort()->values()->all());
});

it('makes no lead of a teacher asking to teach', function () {
    $this->postJson('http://noor.lms.test/api/v1/site/requests', trialForm(['kind' => 'teach', 'subject_ids' => [$this->quran]]))->assertCreated();

    expect($this->inNoor(fn () => Lead::query()->count()))->toBe(0)
        ->and($this->inNoor(fn () => JoinRequest::query()->value('lead_id')))->toBeNull();
});
