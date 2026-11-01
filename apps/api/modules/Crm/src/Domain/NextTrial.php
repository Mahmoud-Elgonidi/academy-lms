<?php

namespace Modules\Crm\Domain;

use Carbon\CarbonImmutable;

/**
 * The facts about one more free trial a family books from an earlier one
 * (docs/architecture/02, section 27, J2): "rebook" after a trial that did
 * not take place (the learner missed it, the teacher did, or the academy
 * cancelled it), or "second" after one that did, with another teacher (the
 * academy's guarantee). NextTrialRules decides from these alone.
 */
final readonly class NextTrial
{
    public function __construct(
        public string $kind,                  // rebook | second
        public bool $familyLook,              // the classic look (a guardian or an adult): money and booking are theirs
        public bool $familyBooks,             // the academy's page is on and families book their own trial
        public bool $secondTrialOn,           // the academy gives a second trial (crm_settings.second_trial)
        public bool $assessed,                // the earlier trial has its assessment (a report)
        public CarbonImmutable $startedAt,    // when the earlier trial began
        public CarbonImmutable $now,
        public bool $studying,                // the learner has a course in the subject that has not ended
        public bool $openTrial,               // the learner has a trial to come in the subject
        public int $openForNumber,            // trials to come on the family's number
        public bool $secondUsed,              // the learner had their second trial in the subject already
        public int $studentMissed,            // trials in the subject the learner did not come to
        public bool $hadTrial,                // the learner took a trial in the subject (it took place)
        public bool $hasNumber = true,        // the family's number is still there (not erased)
    ) {}
}
