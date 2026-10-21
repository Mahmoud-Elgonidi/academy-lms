<?php

use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\People\Actions\Cards\SaveTeacherCard;
use Modules\People\Filament\Teacher\MyCard;
use Modules\People\Models\TeacherPublicCard;
use Modules\People\Privacy\ProfileData;
use Tests\Support\AcademySite;

uses(AcademySite::class);

/*
 * A teacher's card on the academy's page (docs/architecture/02, section 24):
 * written by the teacher alone, in plain words, with a photo if they like
 * (a PNG, JPEG or WebP by its bytes, 1 MB at most, on the public disk under
 * the academy's folder), shown only while they switch it on. It is in their
 * copy of their data, and leaves with their account.
 */

beforeEach(function () {
    $this->withoutDefer();
    Mail::fake();
    Storage::fake('public');
    $this->siteAcademy();
    $this->actAsTenant($this->academy);
    $this->omar = $this->teachers['omar'];   // made no card yet
});

it('lets a teacher write their card in /teach, with a preview of how families see it', function () {
    $this->actingAs($this->omar);
    Filament::setCurrentPanel('teach');

    Livewire::test(MyCard::class)
        ->assertSee(__('people::cards.state.none', [], 'en'))
        ->assertSet('data.display_name', 'Omar Hany')
        ->set('data.display_name', '  Mr   Omar ')
        ->set('data.bio', "Ten years teaching Quran.\n\n\n\nPatient with beginners.")
        ->set('data.shown', true)
        ->call('save')
        ->assertNotified(__('people::cards.saved', [], 'en'))
        ->assertSee('Mr Omar')->assertSee('Beginner');

    $card = TeacherPublicCard::query()->where('teacher_id', $this->omar->id)->sole();
    expect($card->only(['display_name', 'bio', 'shown', 'hidden_by_academy', 'photo_path']))->toBe([
        'display_name' => 'Mr Omar', 'bio' => "Ten years teaching Quran.\n\nPatient with beginners.", 'shown' => true, 'hidden_by_academy' => false, 'photo_path' => null,
    ]);
    $audit = DB::table('audit_logs')->where('action', 'people.card_saved')->sole();
    expect(json_decode($audit->data, true))->toEqual(['teacher' => 'Omar Hany', 'card' => 'shown'])
        ->and($audit->data)->not->toContain('Patient');

    $this->clearTenant();
    expect(collect($this->getJson('http://noor.lms.test/api/v1/site')->json('data.teachers'))->pluck('name')->sort()->values()->all())->toBe(['Mr Omar', 'Ms Sara']);
    $this->get('http://noor.lms.test/teach/my-card')->assertOk();
});

it('keeps the page to teachers', function () {
    $this->actingAs($this->sales);
    expect(MyCard::canAccess())->toBeFalse();
    $this->actingAs($this->omar);
    expect(MyCard::canAccess())->toBeTrue();
});

it('takes a photo by its bytes, small, on the public disk under the academy\'s folder, and lets the old one go', function () {
    $this->actingAs($this->omar);
    $save = fn (?UploadedFile $photo, bool $remove = false) => app(SaveTeacherCard::class)->handle($this->omar->id, ['display_name' => 'Mr Omar', 'shown' => true], $photo, $remove);

    $first = $save(UploadedFile::fake()->image('me.png', 200, 200))->photo_path;
    expect($first)->toStartWith("site/{$this->academy->id}/teachers/")->toEndWith('.png')
        ->and(Storage::disk('public')->exists($first))->toBeTrue();

    $second = $save(UploadedFile::fake()->image('me.jpg', 200, 200))->photo_path;
    expect($second)->toEndWith('.jpg')
        ->and(Storage::disk('public')->exists($first))->toBeFalse()
        ->and(Storage::disk('public')->exists($second))->toBeTrue();

    // A page dressed as a picture (its bytes say so, whatever its name), a drawing that can carry a script, a picture too big:
    // refused, and the card keeps its photo.
    $page = tempnam(sys_get_temp_dir(), 'card');
    file_put_contents($page, '<html><body><script>alert(1)</script></body></html>');
    foreach ([
        new UploadedFile($page, 'me.png', 'image/png', null, true),
        UploadedFile::fake()->create('me.svg', 4, 'image/svg+xml'),
        UploadedFile::fake()->image('big.png')->size(1500),
    ] as $bad) {
        expect(fn () => $save($bad))->toThrow(ValidationException::class);
    }
    expect(TeacherPublicCard::query()->where('teacher_id', $this->omar->id)->value('photo_path'))->toBe($second)
        ->and(Storage::disk('public')->allFiles())->toBe([$second]);

    $save(null, remove: true);
    expect(TeacherPublicCard::query()->where('teacher_id', $this->omar->id)->value('photo_path'))->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('is the teacher\'s alone to write, with a name and plain words held to their lengths', function () {
    $this->actingAs($this->owner);
    expect(fn () => app(SaveTeacherCard::class)->handle($this->omar->id, ['display_name' => 'Omar']))->toThrow(AuthorizationException::class);

    $this->actingAs($this->omar);
    foreach ([['display_name' => ' '], ['display_name' => str_repeat('x', 61)], ['display_name' => 'Omar', 'bio' => str_repeat('x', 281)]] as $data) {
        expect(fn () => app(SaveTeacherCard::class)->handle($this->omar->id, $data))->toThrow(ValidationException::class);
    }
    // Someone who does not teach here has no card to write.
    $this->actingAs($this->sales);
    expect(fn () => app(SaveTeacherCard::class)->handle($this->sales->id, ['display_name' => 'Reem']))->toThrow(AuthorizationException::class);
});

it('tells the teacher when the academy has taken their card off, never why', function () {
    $this->actingAs($this->teachers['huda']);
    Filament::setCurrentPanel('teach');

    Livewire::test(MyCard::class)->assertSee(__('people::cards.state.hidden', [], 'en'));
});

it('puts the card in the teacher\'s copy, and removes it and its photo with their account', function () {
    $this->actingAs($this->omar);
    $card = app(SaveTeacherCard::class)->handle($this->omar->id, ['display_name' => 'Mr Omar', 'bio' => 'Quran teacher', 'shown' => true], UploadedFile::fake()->image('me.webp', 100, 100));
    $this->actingAs($this->owner);

    $copy = app(ProfileData::class)->export($this->omar->id)->data['public_card'];
    expect($copy)->toMatchArray(['display_name' => 'Mr Omar', 'bio' => 'Quran teacher', 'photo' => $card->photo_path, 'shown' => true, 'hidden_by_academy' => false]);

    DB::transaction(fn () => expect(app(ProfileData::class)->erase($this->omar->id)['public_card'])->toBe(1));
    expect(TeacherPublicCard::query()->where('teacher_id', $this->omar->id)->exists())->toBeFalse()
        ->and(Storage::disk('public')->exists($card->photo_path))->toBeFalse()
        ->and(app(ProfileData::class)->export($this->omar->id)->data['public_card'])->toBeNull();
});

it('lets a teacher read and write their card from the apps, as in /teach', function () {
    $this->clearTenant();
    $api = fn ($user) => $this->actingAs($user, 'sanctum')->withHeaders(['X-Academy' => 'noor', 'Accept-Language' => 'en', 'Accept' => 'application/json']);

    $api($this->omar)->getJson('/api/v1/teacher/card')->assertOk()
        ->assertJsonPath('data.display_name', 'Omar Hany')->assertJsonPath('data.shown', false)
        ->assertJsonPath('data.state', 'none')->assertJsonPath('data.photo_url', null);

    $saved = $api($this->omar)->post('/api/v1/teacher/card', [
        'display_name' => 'Mr Omar', 'bio' => 'Patient with beginners.', 'shown' => '1', 'photo' => UploadedFile::fake()->image('me.png', 120, 120),
    ])->assertOk()->assertJsonPath('data.state', 'on')->assertJsonPath('data.bio', 'Patient with beginners.');
    expect($saved->json('data.photo_url'))->toContain("site/{$this->academy->id}/teachers/")
        ->and($saved->json('data.subjects'))->not->toBeEmpty()
        ->and($saved->getContent())->not->toContain((string) $this->omar->email);

    // Switched off, the photo taken away; a name too long is refused in the reader's words.
    $api($this->omar)->post('/api/v1/teacher/card', ['display_name' => 'Mr Omar', 'shown' => '0', 'remove_photo' => '1'])
        ->assertOk()->assertJsonPath('data.state', 'off')->assertJsonPath('data.photo_url', null);
    $api($this->omar)->post('/api/v1/teacher/card', ['display_name' => str_repeat('x', 61)])->assertUnprocessable()->assertJsonStructure(['errors' => ['display_name']]);

    // Taken off by the academy: said, never why. Someone who does not teach has no card.
    $api($this->teachers['huda'])->getJson('/api/v1/teacher/card')->assertOk()->assertJsonPath('data.state', 'hidden');
    $api($this->sales)->getJson('/api/v1/teacher/card')->assertForbidden();
    $api($this->sales)->post('/api/v1/teacher/card', ['display_name' => 'Reem'])->assertForbidden();
});
