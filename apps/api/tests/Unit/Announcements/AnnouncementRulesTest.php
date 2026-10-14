<?php

use Modules\Announcements\Domain\AnnouncementRules;
use Modules\Announcements\Domain\Audience;

/*
 * What an announcement may be and whom it may be for (docs/architecture/02,
 * section 20): a one-line title and plain words with their line breaks,
 * within their limits; the inbox always, push and email if chosen, never
 * WhatsApp; a few a day. Only "students" reaches a child under a guardian.
 * (Their purity is in tests/Arch.)
 */

it('keeps a title to one line and the words with their line breaks, without control characters', function () {
    expect(AnnouncementRules::title("  Eid\tholiday\n next week \u{0007} "))->toBe('Eid holiday next week')
        ->and(AnnouncementRules::body("  First line.  \r\nSecond line.\r\n\r\n\r\n\r\nAfter a gap.\u{0000}  "))->toBe("First line.\nSecond line.\n\nAfter a gap.")
        // Marks of direction and joiners stay: Arabic needs them.
        ->and(AnnouncementRules::body("إجازة\u{200F} العيد\u{200C}"))->toBe("إجازة\u{200F} العيد\u{200C}")
        ->and(AnnouncementRules::title(" \n\t "))->toBe('');
});

it('says what is wrong with the words and the channels, by field', function () {
    expect(AnnouncementRules::problems('Eid', 'Closed on Tuesday.', ['push', 'email']))->toBe([])
        ->and(AnnouncementRules::problems('', '', []))->toBe(['title' => 'required', 'body' => 'required'])
        ->and(AnnouncementRules::problems(str_repeat('ع', 120), str_repeat('ك', 1000), []))->toBe([])
        ->and(AnnouncementRules::problems(str_repeat('ع', 121), str_repeat('ك', 1001), []))->toBe(['title' => 'too_long', 'body' => 'too_long'])
        // WhatsApp's approved template takes no free words.
        ->and(AnnouncementRules::problems('Eid', 'Closed.', ['whatsapp']))->toBe(['channels' => 'channel'])
        ->and(AnnouncementRules::channels(['email', 'push', 'email']))->toBe(['push', 'email']);
});

it('allows a few a day', function () {
    expect(AnnouncementRules::left(0))->toBe(AnnouncementRules::PER_DAY)
        ->and(AnnouncementRules::left(4))->toBe(1)
        ->and(AnnouncementRules::left(5))->toBe(0)
        ->and(AnnouncementRules::left(9))->toBe(0);
});

it('knows each audience and reaches a child under a guardian only when students are chosen outright', function () {
    $families = Audience::of('families');
    $students = Audience::of('students', 'kids');
    $subject = Audience::of('subject', null, 'subject-1');
    $teachers = Audience::of('teachers', '', '');

    expect([$families->reachesGuardians(), $families->reachesStudents(), $families->reachesManagedChildren()])->toBe([true, true, false])
        ->and([Audience::of('guardians')->reachesGuardians(), Audience::of('guardians')->reachesStudents()])->toBe([true, false])
        ->and([$students->reachesGuardians(), $students->reachesStudents(), $students->reachesManagedChildren(), $students->stage])->toBe([false, true, true, 'kids'])
        ->and([$subject->reachesGuardians(), $subject->reachesStudents(), $subject->reachesManagedChildren(), $subject->subjectId])->toBe([true, true, false, 'subject-1'])
        ->and([$teachers->forTeachers(), $teachers->reachesGuardians(), $teachers->reachesStudents(), $teachers->stage, $teachers->subjectId])->toBe([true, false, false, null, null]);
});

it('refuses an audience that does not hold together', function (string $kind, ?string $stage, ?string $subject, string $why) {
    expect(fn () => Audience::of($kind, $stage, $subject))->toThrow(InvalidArgumentException::class, $why);
})->with([
    'an unknown kind' => ['everyone', null, null, 'kind'],
    'a stage for guardians' => ['guardians', 'kids', null, 'stage'],
    'an unknown stage' => ['students', 'toddlers', null, 'stage'],
    'a subject without one' => ['subject', null, null, 'subject'],
    'a subject for teachers' => ['teachers', null, 'subject-1', 'subject'],
]);
