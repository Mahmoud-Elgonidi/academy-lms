<?php

use Carbon\CarbonImmutable;
use Modules\SharedKernel\Time\LocalTimeKind;
use Modules\SharedKernel\Time\LocalTimeResolver;
use Modules\SharedKernel\Time\Timezones;

/*
 * Real 2027 summer-time edges from docs/architecture/02-domain-rules.md, section 1.
 * These depend on the system tzdata: if a government changes its rules, update the
 * tzdata in the image and these expectations together.
 */

beforeEach(function () {
    $this->resolver = new LocalTimeResolver;
});

function utcOf(string $date, string $time, string $tz): string
{
    return (new LocalTimeResolver)->resolve($date, $time, $tz)->utc->format('Y-m-d H:i');
}

it('resolves an ordinary time exactly', function () {
    $r = $this->resolver->resolve('2027-03-14', '20:00', 'Asia/Riyadh');

    expect($r->utc->format('Y-m-d H:i'))->toBe('2027-03-14 17:00')
        ->and($r->kind)->toBe(LocalTimeKind::Exact)
        ->and($r->isExact())->toBeTrue();
});

it('moves a time that does not exist forward by the gap', function (string $date, string $time, string $tz, string $utc, string $localAfter) {
    $r = $this->resolver->resolve($date, $time, $tz);

    expect($r->kind)->toBe(LocalTimeKind::ShiftedForward)
        ->and($r->shiftMinutes)->toBe(60)
        ->and($r->utc->format('Y-m-d H:i'))->toBe($utc)
        ->and($r->utc->setTimezone($tz)->format('H:i'))->toBe($localAfter);
})->with([
    'Cairo, Friday 30 April 2027 (00:00 → 01:00)' => ['2027-04-30', '00:30', 'Africa/Cairo', '2027-04-29 22:30', '01:30'],
    'London, Sunday 28 March 2027 (01:00 → 02:00)' => ['2027-03-28', '01:30', 'Europe/London', '2027-03-28 01:30', '02:30'],
    'Sydney, Sunday 3 October 2027 (02:00 → 03:00)' => ['2027-10-03', '02:30', 'Australia/Sydney', '2027-10-02 16:30', '03:30'],
]);

it('takes the earlier of a time that happens twice', function (string $date, string $time, string $tz, string $utc) {
    $r = $this->resolver->resolve($date, $time, $tz);

    expect($r->kind)->toBe(LocalTimeKind::Ambiguous)
        ->and($r->utc->format('Y-m-d H:i'))->toBe($utc);
})->with([
    'Cairo, Thursday 28 October 2027 (24:00 → 23:00)' => ['2027-10-28', '23:30', 'Africa/Cairo', '2027-10-28 20:30'],
    'New York, Sunday 7 November 2027 (02:00 → 01:00)' => ['2027-11-07', '01:30', 'America/New_York', '2027-11-07 05:30'],
]);

it('keeps a teacher-anchored 19:00 Cairo slot right for students in Riyadh and London through 2027', function (string $date, string $riyadh, string $london) {
    $utc = (new LocalTimeResolver)->resolve($date, '19:00', 'Africa/Cairo')->utc;

    expect($utc->setTimezone('Asia/Riyadh')->format('H:i'))->toBe($riyadh)
        ->and($utc->setTimezone('Europe/London')->format('H:i'))->toBe($london);
})->with([
    'before any switch' => ['2027-03-20', '20:00', '17:00'],
    'after the EU switch' => ['2027-04-03', '20:00', '18:00'],
    'after the Egypt switch' => ['2027-05-08', '19:00', '17:00'],
    'Egypt back, UK not yet' => ['2027-10-29', '20:00', '18:00'],
    'both back' => ['2027-11-06', '20:00', '17:00'],
]);

it('refuses unknown timezones and offsets', function (string $tz) {
    (new LocalTimeResolver)->resolve('2027-01-01', '10:00', $tz);
})->with(['+03:00', 'GMT+3', 'Cairo', ''])->throws(InvalidArgumentException::class);

it('tells zones that keep the same clock from ones that only agree sometimes', function () {
    $from = new DateTimeImmutable('2027-01-01');

    expect(Timezones::sameClock('Asia/Riyadh', 'Asia/Kuwait', $from))->toBeTrue()
        ->and(Timezones::sameClock('Africa/Cairo', 'Asia/Riyadh', $from))->toBeFalse()
        ->and(Timezones::isValid('Asia/Dubai'))->toBeTrue()
        ->and(Timezones::isValid('+04:00'))->toBeFalse();
});

it('returns immutable UTC instants', function () {
    $r = (new LocalTimeResolver)->resolve('2027-06-01', '09:15', 'America/New_York');

    expect($r->utc)->toBeInstanceOf(CarbonImmutable::class)
        ->and($r->utc->getTimezone()->getName())->toBe('UTC')
        ->and(utcOf('2027-06-01', '09:15', 'America/New_York'))->toBe('2027-06-01 13:15');
});
