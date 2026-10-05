<?php

namespace Modules\SharedKernel\Time;

enum LocalTimeKind: string
{
    /** The wall-clock time exists exactly once. */
    case Exact = 'exact';

    /** The time does not exist (clocks jumped forward); moved later by the gap. */
    case ShiftedForward = 'shifted_forward';

    /** The time happens twice (clocks went back); the earlier one was taken. */
    case Ambiguous = 'ambiguous';
}
