<?php

namespace Modules\Learning\Domain\Journey;

/** A guardian's cheers on their child's journey (docs/architecture/02, section 18): a few a day, so each stays special. */
final class Cheers
{
    /** Cheers from one guardian to one learner in a day (the last 24 hours). */
    public const PER_DAY = 5;
}
