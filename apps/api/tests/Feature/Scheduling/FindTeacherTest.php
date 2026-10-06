<?php

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\People\Contracts\DTOs\TeacherProfileData;
use Modules\People\Contracts\PeopleApi;
use Modules\Scheduling\Actions\ConfirmBooking;
use Modules\Scheduling\Actions\PlaceBookingHold;
use Modules\Scheduling\Actions\ScheduleSession;
use Modules\Scheduling\Contracts\DTOs\NewSession;
use Modules\Scheduling\Contracts\DTOs\TeacherSearch;
use Modules\Scheduling\Domain\Availability\StudentPreference;
use Modules\Scheduling\Domain\Enums\SessionType;
use Modules\Scheduling\Domain\Models\BookingHold;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Models\ShiftSchedule;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\Scheduling\Queries\FindTeachersQuery;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 07:00', 'UTC'));
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->quran = app(CatalogApi::class)->createSubject('قرآن', 'Quran');
    $this->coordinator = User::factory()->withRole(Role::Scheduler)->create();
    $this->other = User::factory()->withRole(Role::Scheduler)->create();
    $this->student = User::factory()->withRole(Role::Student)->create(['timezone' => 'Europe/London']);

    // Nour (female, Cairo) and Mahmoud (male, Cairo): Saturdays and Tuesdays 17:00–21:00 since September.
    foreach (['nour' => 'female', 'mahmoud' => 'male'] as $key => $gender) {
        $this->{$key} = User::factory()->withRole(Role::Teacher)->create(['name' => $key, 'timezone' => 'Africa/Cairo']);
        app(PeopleApi::class)->saveTeacherProfile(new TeacherProfileData($this->{$key}->id, $gender, ['ar', 'en'], [$this->quran]));
        $schedule = ShiftSchedule::query()->create(['teacher_id' => $this->{$key}->id, 'version' => 1, 'timezone' => 'Africa/Cairo', 'effective' => '[2026-09-01,)']);
        foreach (WeeklyShifts::fromArray([['weekday' => 6, 'start' => '17:00', 'end' => '21:00'], ['weekday' => 2, 'start' => '17:00', 'end' => '21:00']], 'Africa/Cairo')->blocks as $b) {
            $schedule->rules()->create(['teacher_id' => $this->{$key}->id, 'weekday' => $b->weekday, 'local_start' => $b->start(), 'duration_min' => $b->durationMin]);
        }
    }
});

afterEach(fn () => CarbonImmutable::setTestNow());

function search(string $gender = 'any', ?string $searcher = null, int $minCoverage = 8): array
{
    $pref = new StudentPreference('Europe/London', StudentPreference::windowsFromHours([6 => [15, 16, 17], 2 => [15, 16, 17]]), 2, 30, CarbonImmutable::parse('2026-10-06'), 8, $minCoverage);

    return app(FindTeachersQuery::class)->run(new TeacherSearch(test()->quran, $pref, $gender, searcherId: $searcher));
}

it('finds teachers of the subject who are free at the student\'s times, with a suggested pattern', function () {
    $results = search();

    expect(collect($results)->pluck('teacher.name')->sort()->values()->all())->toBe(['mahmoud', 'nour'])
        ->and($results[0]['pattern'])->toHaveCount(2)
        ->and($results[0]['score'])->toBeGreaterThan(80);
});

it('honours "female only"', function () {
    expect(collect(search('female_only'))->pluck('teacher.name')->all())->toBe(['nour']);
});

it('drops a time from the strict search when a booking takes one of its weeks', function () {
    // Nour busy on Saturday 24 October at 18:00 Cairo (16:00 London).
    app(ScheduleSession::class)->handle(new NewSession($this->nour->id, User::factory()->withRole(Role::Student)->create()->id, $this->quran, CarbonImmutable::parse('2026-10-24 18:00', 'Africa/Cairo'), 30, 'Africa/Cairo'));

    $nour = fn (array $results) => collect($results)->first(fn ($r) => $r['teacher']->name === 'nour');
    $has = fn (array $result) => collect($result['candidates'])->contains(fn ($c) => $c->key() === '6|18:00');

    expect($has($nour(search())))->toBeFalse()
        ->and($has($nour(search(minCoverage: 7))))->toBeTrue();
});

it('holds the chosen times for ten minutes: taken for other coordinators, not for the one holding', function () {
    $nour = collect(search())->first(fn ($r) => $r['teacher']->name === 'nour');
    $chosen = collect($nour['candidates'])->first(fn ($c) => $c->key() === '6|18:00');

    $batch = app(PlaceBookingHold::class)->handle($this->nour->id, $this->student->id, $chosen->occurrences, 30, $this->coordinator->id);

    expect(BookingHold::query()->where('batch_id', $batch)->count())->toBe(8);
    $others = collect(search(searcher: $this->other->id))->first(fn ($r) => $r['teacher']->name === 'nour');
    $mine = collect(search(searcher: $this->coordinator->id))->first(fn ($r) => $r['teacher']->name === 'nour');
    expect(collect($others['candidates'])->contains(fn ($c) => $c->key() === '6|18:00'))->toBeFalse()
        ->and(collect($mine['candidates'])->contains(fn ($c) => $c->key() === '6|18:00'))->toBeTrue();

    expect(fn () => app(PlaceBookingHold::class)->handle($this->nour->id, null, $chosen->occurrences, 30, $this->other->id))
        ->toThrow(ValidationException::class);
});

it('books a free trial from a hold, then releases it', function () {
    $nour = collect(search())->first(fn ($r) => $r['teacher']->name === 'nour');
    $times = array_merge(...array_map(fn ($c) => $c->occurrences, $nour['pattern']));
    $batch = app(PlaceBookingHold::class)->handle($this->nour->id, $this->student->id, $times, 30, $this->coordinator->id);

    $trial = app(ConfirmBooking::class)->trial($batch, $this->student->id, $this->quran, $this->coordinator->id);

    expect($trial->type)->toBe(SessionType::Trial)
        ->and($trial->funding_status->value)->toBe('free')
        ->and($trial->starts_at->getTimestamp())->toBe(min($times))
        ->and(Session::query()->count())->toBe(1)
        ->and(BookingHold::query()->count())->toBe(0);
});

it('refuses to book from an expired hold', function () {
    $batch = app(PlaceBookingHold::class)->handle($this->nour->id, null, [CarbonImmutable::parse('2026-10-10 17:00', 'Africa/Cairo')->getTimestamp()], 30, $this->coordinator->id);
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(11));

    expect(fn () => app(ConfirmBooking::class)->trial($batch, $this->student->id, $this->quran, $this->coordinator->id))
        ->toThrow(ValidationException::class);
});
