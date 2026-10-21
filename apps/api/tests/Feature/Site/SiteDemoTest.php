<?php

use Database\Seeders\DemoCatchUpSeeder;
use Modules\Billing\Models\PriceRegion;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\People\Models\TeacherPublicCard;
use Modules\Site\Domain\SiteContent;
use Modules\Site\Models\SiteSettings;

/*
 * A demo already on a server takes the academy's page on the next deploy
 * (DemoCatchUpSeeder): the page on with its words in both languages, three
 * teachers' cards, and its price regions naming their countries. Each step
 * once: what the demo's people changed since stays as they left it.
 */

beforeEach(function () {
    $this->demo = $this->actAsTenant($this->createAcademy(['slug' => 'demo']));
    foreach (['nour@demo.test', 'sara@demo.test', 'khaled@demo.test', 'mahmoud@demo.test'] as $email) {
        $teacher = User::factory()->withRole(Role::Teacher)->create(['email' => $email]);
        app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($teacher->id, 'female'));
    }
    foreach (['SAR', 'EGP', 'EUR'] as $i => $currency) {
        PriceRegion::query()->create(['name_ar' => $currency, 'name_en' => $currency, 'currency' => $currency, 'is_default' => $i === 0, 'sort' => $i]);
    }
    $this->clearTenant();
});

it('brings a demo already there its page, its teachers\' cards and its regions\' countries, once', function () {
    $this->artisan('db:seed', ['--class' => DemoCatchUpSeeder::class, '--force' => true])->assertSuccessful();

    $this->actAsTenant($this->demo);
    $site = SiteSettings::current();
    expect($site->enabled)->toBeTrue()
        ->and($site->content['sections'])->toBe(SiteContent::SECTIONS)
        ->and($site->content['testimonials'])->toHaveCount(2)
        ->and($site->content['hero']['title_ar'])->not->toBe('')->and($site->content['hero']['title_en'])->not->toBe('')
        ->and(TeacherPublicCard::query()->where('shown', true)->whereNull('photo_path')->count())->toBe(3)
        ->and(PriceRegion::query()->orderBy('sort')->pluck('countries', 'currency')->all())->toBe(['SAR' => ['SA', 'KW', 'QA', 'BH', 'OM'], 'EGP' => ['EG'], 'EUR' => ['DE', 'FR', 'NL', 'BE', 'IT', 'ES', 'IE', 'AT']]);

    // What the demo's people changed since stays.
    $site->forceFill(['enabled' => false])->save();
    TeacherPublicCard::query()->update(['shown' => false]);
    PriceRegion::query()->where('currency', 'EGP')->update(['countries' => json_encode(['EG', 'SD'])]);
    $this->clearTenant();
    $this->artisan('db:seed', ['--class' => DemoCatchUpSeeder::class, '--force' => true])->assertSuccessful();

    $this->actAsTenant($this->demo);
    expect(SiteSettings::current()->enabled)->toBeFalse()
        ->and(TeacherPublicCard::query()->count())->toBe(3)
        ->and(TeacherPublicCard::query()->where('shown', true)->count())->toBe(0)
        ->and(PriceRegion::query()->where('currency', 'EGP')->value('countries'))->toBe(['EG', 'SD']);
    $this->getJson('http://demo.lms.test/api/v1/site')->assertNotFound();
});
