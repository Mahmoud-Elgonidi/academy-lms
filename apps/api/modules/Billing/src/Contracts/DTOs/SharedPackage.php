<?php

namespace Modules\Billing\Contracts\DTOs;

/**
 * A student's shared package on a plan ("12 lessons in any subject"): the
 * balance every course on it draws on, whether its first block is paid
 * (a course joining a paid package starts at once), and the subjects on it.
 */
final readonly class SharedPackage
{
    /** @param  list<string>  $subjectIds */
    public function __construct(
        public string $subscriptionId,
        public string $creditAccountId,
        public bool $paid,
        public array $subjectIds,
    ) {}
}
