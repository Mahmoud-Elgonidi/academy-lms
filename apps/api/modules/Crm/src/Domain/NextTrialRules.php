<?php

namespace Modules\Crm\Domain;

/**
 * Whether a family may book one more free trial from an earlier one, and if
 * not why, in the order a family would want to hear it (docs/architecture/
 * 02, section 27, J2). Pure: Crm's BookNextTrial gathers the facts under the
 * number's lock and asks again before booking.
 *
 * - Only the classic look books (a guardian or an adult; never a child's own screen).
 * - A second trial: while the academy gives it, after an assessed trial, once per learner and
 *   subject, within SECOND_WITHIN_DAYS of the first, with another teacher.
 * - Booking again after a missed trial: not once a trial in the subject took place; a learner who
 *   missed MAX_STUDENT_MISSES trials talks to the academy. A teacher's absence or the academy's
 *   cancellation never counts against the family.
 * - Both: never for a learner who studies the subject; one trial to come per learner and subject;
 *   MAX_OPEN_PER_NUMBER per number (J1's); only while families book their own trial.
 */
final class NextTrialRules
{
    public const SECOND_WITHIN_DAYS = 14;

    public const MAX_STUDENT_MISSES = 2;

    public const MAX_OPEN_PER_NUMBER = 3;

    /** @return ?string null when it may be booked; else the reason's key (crm::report.next.why.{key}) */
    public static function whyNot(NextTrial $f): ?string
    {
        $second = $f->kind === 'second';

        return match (true) {
            ! $f->familyLook => 'family_only',
            $second && ! $f->secondTrialOn => 'second_off',
            $second && ! $f->assessed => 'not_assessed',
            ! $f->hasNumber => 'no_number',
            $f->studying => 'already_student',
            $second && $f->secondUsed => 'second_used',
            $second && $f->now->greaterThan($f->startedAt->addDays(self::SECOND_WITHIN_DAYS)) => 'second_late',
            ! $second && $f->hadTrial => 'had_trial',
            ! $second && $f->studentMissed >= self::MAX_STUDENT_MISSES => 'missed_twice',
            $f->openTrial => 'trial_open',
            $f->openForNumber >= self::MAX_OPEN_PER_NUMBER => 'too_many_open',
            ! $f->familyBooks => 'staff_books',
            default => null,
        };
    }

    /** Whether the family should be offered it at all (a reason it shows), or it is not theirs to see (the academy gives none). */
    public static function shown(NextTrial $f): bool
    {
        return $f->familyLook && ($f->kind !== 'second' || ($f->secondTrialOn && $f->assessed));
    }
}
