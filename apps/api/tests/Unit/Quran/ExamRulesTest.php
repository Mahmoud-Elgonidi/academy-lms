<?php

use Carbon\CarbonImmutable;
use Modules\Quran\Certificates\Certificates;
use Modules\Quran\Domain\Certificates\CertificateNumber;
use Modules\Quran\Domain\Exams\Examiners;
use Modules\Quran\Domain\Exams\ExamOutcome;

/*
 * Exams and certificates (docs/architecture/02, section 26): who may examine
 * by the academy's choice, what a fail must say, a certificate's number and
 * code, and the name the public verification shows.
 */

it('lets examine who the academy\'s choice allows, never the student, the course\'s teacher only under "any teacher"', function () {
    // examines (quran.examine), teaches the Quran, is the course's teacher, is the student
    expect(Examiners::refusal('other_teacher', true, false, false, false))->toBeNull()
        ->and(Examiners::refusal('other_teacher', false, true, false, false))->toBeNull()
        ->and(Examiners::refusal('other_teacher', false, true, true, false))->toBe('own_teacher')
        ->and(Examiners::refusal('other_teacher', true, false, true, false))->toBe('own_teacher')
        ->and(Examiners::refusal('other_teacher', false, false, false, false))->toBe('not_examiner')
        ->and(Examiners::refusal('supervisor', false, true, false, false))->toBe('not_examiner')
        ->and(Examiners::refusal('supervisor', true, false, false, false))->toBeNull()
        ->and(Examiners::refusal('any_teacher', false, true, true, false))->toBeNull()
        ->and(Examiners::refusal('any_teacher', true, true, false, true))->toBe('student');
});

it('passes at the program\'s grade; a fail needs a note and a retake day after today, within 180 days', function () {
    $today = CarbonImmutable::parse('2026-10-08');
    expect(ExamOutcome::passed('very_good', 'good'))->toBeTrue()
        ->and(ExamOutcome::passed('acceptable', 'good'))->toBeFalse()
        ->and(ExamOutcome::missing(true, null, null, $today))->toBe([])
        ->and(ExamOutcome::missing(false, ' ', null, $today))->toBe(['note', 'retake_on'])
        ->and(ExamOutcome::missing(false, 'Review', CarbonImmutable::parse('2026-10-08'), $today))->toBe(['retake_on'])
        ->and(ExamOutcome::missing(false, 'Review', CarbonImmutable::parse('2026-10-09'), $today))->toBe([])
        ->and(ExamOutcome::missing(false, 'Review', $today->addDays(180), $today))->toBe([])
        ->and(ExamOutcome::missing(false, 'Review', $today->addDays(181), $today))->toBe(['retake_on']);
});

it('numbers certificates Q-{year}-{six digits} and reads a code back whatever its case or dashes', function () {
    expect(CertificateNumber::format(2026, 123))->toBe('Q-2026-000123')
        ->and(fn () => CertificateNumber::format(2026, 0))->toThrow(InvalidArgumentException::class);
    $code = CertificateNumber::code(random_int(...));
    expect(strlen($code))->toBe(16)->and(strspn($code, CertificateNumber::ALPHABET))->toBe(16)
        ->and(CertificateNumber::display('ABCDEFGHJKMNPQRS'))->toBe('ABCD-EFGH-JKMN-PQRS')
        ->and(CertificateNumber::normalize(' abcd-efgh-jkmn-pqrs '))->toBe('ABCDEFGHJKMNPQRS')
        ->and(CertificateNumber::normalize('ABCD-EFGH-JKMN-PQR0'))->toBeNull()   // 0 is never in a code
        ->and(CertificateNumber::normalize('short'))->toBeNull();
    // Codes do not repeat.
    $codes = array_map(fn () => CertificateNumber::code(random_int(...)), range(1, 200));
    expect(count(array_unique($codes)))->toBe(200);
});

it('shows the public the first name and the last name\'s initial, past an Arabic article', function () {
    expect(Certificates::shortName('Ahmad Ali Hassan'))->toBe('Ahmad H.')
        ->and(Certificates::shortName('زياد عمر الهاشمي'))->toBe('زياد ه.')
        ->and(Certificates::shortName('Lina'))->toBe('Lina')
        ->and(Certificates::shortName('  '))->toBe('');
});
