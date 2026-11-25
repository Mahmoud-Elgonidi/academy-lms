<?php

use Illuminate\Support\Facades\Mail;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Communication\Models\Notification;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Tenancy\AcademyAudience;
use Modules\SharedKernel\Tenancy\Audience;
use Modules\SharedKernel\Tenancy\AudienceTranslator;

/*
 * An adults academy's words (docs/architecture/02, section 29): a module's
 * lang/{locale}/adults/{file}.php is read first, key by key, then the base
 * file; families academies, no academy, JSON and files without a namespace
 * never change. The fixture namespace "audfix" is tests/Fixtures/lang.
 */

beforeEach(function () {
    app('translator')->addNamespace('audfix', dirname(__DIR__, 2).'/Fixtures/lang');
    $this->adults = $this->createAcademy(['slug' => 'hm-trust', 'audience' => 'adults']);
    $this->families = $this->createAcademy(['slug' => 'noor']);
});

it('is Laravel\'s translator, with the adults words over it', function () {
    expect(app('translator'))->toBeInstanceOf(AudienceTranslator::class)
        ->and(app('validator')->make([], [])->getTranslator())->toBe(app('translator'));
});

it('reads the adults words first in an adults academy, and the base words everywhere else', function () {
    // No academy, a families academy: the base file.
    expect(__('audfix::greet.hello', ['name' => 'Sara'], 'en'))->toBe('Welcome, Sara and your family');
    $this->actAsTenant($this->families);
    expect(__('audfix::greet.hello', ['name' => 'Sara'], 'en'))->toBe('Welcome, Sara and your family')
        ->and(trans_choice('audfix::greet.learners', 3, [], 'en'))->toBe('3 children');

    $this->actAsTenant($this->adults);
    expect(app(AcademyAudience::class)->adultsOnly())->toBeTrue()
        ->and(__('audfix::greet.hello', ['name' => 'Sara'], 'en'))->toBe('Welcome, Sara')
        ->and(trans_choice('audfix::greet.learners', 1, [], 'en'))->toBe('one learner')
        ->and(trans_choice('audfix::greet.learners', 3, [], 'en'))->toBe('3 learners')
        // A key the variant leaves out comes from the base file.
        ->and(__('audfix::greet.bye', [], 'en'))->toBe('Goodbye')
        // A group is the base with the variant's keys over it.
        ->and(__('audfix::greet.group', [], 'en'))->toBe(['a' => 'A for professionals', 'b' => 'B for everyone'])
        ->and(__('audfix::greet.group.b', [], 'en'))->toBe('B for everyone')
        ->and(trans()->has('audfix::greet.hello', 'en'))->toBeTrue()
        ->and(trans()->has('audfix::greet.nothing', 'en'))->toBeFalse()
        ->and(__('audfix::greet.nothing', [], 'en'))->toBe('audfix::greet.nothing');

    // Each language in turn: Arabic's own words (adults, else base) before English's.
    expect(__('audfix::greet.bye', [], 'ar'))->toBe('إلى اللقاء، ونتمنى لك تدريبًا موفقًا')
        ->and(__('audfix::greet.hello', ['name' => 'سارة'], 'ar'))->toBe('أهلًا بك يا سارة وبأسرتك')
        ->and(__('audfix::greet.only_english', [], 'ar'))->toBe('Adults English only');

    // Files without a namespace and the JSON lines are never changed.
    expect(__('validation.required', ['attribute' => 'name'], 'en'))->toBe('The name field is required.');

    // Back to a families academy in the same process (a worker moving on): the base words again.
    $this->actAsTenant($this->families);
    expect(__('audfix::greet.hello', ['name' => 'Sara'], 'en'))->toBe('Welcome, Sara and your family');
    $this->clearTenant();
    expect(__('audfix::greet.group.a', [], 'en'))->toBe('A for families');
});

it('writes each recipient\'s notification in their academy\'s words', function () {
    Mail::fake();
    app(NotificationsApi::class)->define(new TemplateDefinition(
        key: 'audfix.hello', category: 'staff', title: 'audfix::greet.hello', body: 'audfix::greet.bye', audience: 'audfix::greet.bye',
        channels: ['email'], sample: ['name' => 'Sara', 'count' => '2'],
    ));

    $send = function ($academy, string $locale): string {
        $this->actAsTenant($academy);
        $user = User::factory()->withRole(Role::Student)->create(['locale' => $locale]);
        app(NotificationsApi::class)->send(new Message('audfix.hello', [$user->id], ['name' => new Translatable('audfix::greet.learners', count: 2)]));

        return Notification::query()->where('user_id', $user->id)->sole()->title.' / '.Notification::query()->where('user_id', $user->id)->sole()->body;
    };

    expect($send($this->adults, 'en'))->toBe('Welcome, 2 learners / Goodbye')
        ->and($send($this->families, 'en'))->toBe('Welcome, 2 children and your family / Goodbye')
        ->and($send($this->adults, 'ar'))->toBe('أهلًا بك يا 2 learners وبأسرتك / إلى اللقاء، ونتمنى لك تدريبًا موفقًا');
});

it('reads whom an academy teaches from its tenant, and families with none', function () {
    $audience = app(AcademyAudience::class);
    expect($audience->current())->toBe(Audience::Families)->and($audience->adultsOnly())->toBeFalse()
        ->and($audience->forAcademy($this->adults->id))->toBe(Audience::Adults)
        ->and($audience->forAcademy($this->families->id))->toBe(Audience::Families)
        ->and($audience->forAcademy('00000000-0000-0000-0000-000000000000'))->toBe(Audience::Families)
        ->and($audience->forAcademy('not-an-id'))->toBe(Audience::Families);

    $this->actAsTenant($this->adults);
    expect($audience->current())->toBe(Audience::Adults)->and($audience->adultsOnly())->toBeTrue()
        ->and($audience->forAcademy($this->families->id))->toBe(Audience::Families)
        ->and($this->adults->audience)->toBe(Audience::Adults)
        ->and($this->families->audience)->toBe(Audience::Families)
        ->and(Audience::of('nonsense'))->toBe(Audience::Families);
});
