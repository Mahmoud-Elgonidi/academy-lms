<?php

namespace Modules\Safeguarding\Enums;

/** new (nobody took it yet) → open (a lead is looking into it) → closed (with an outcome); a closed one can be opened again. */
enum ConcernStatus: string
{
    case New = 'new';
    case Open = 'open';
    case Closed = 'closed';

    /** What the reporter reads: received, being looked into, closed (never the academy's notes or outcome). */
    public function forReporter(): string
    {
        return __('safeguarding::concerns.reporter_status.'.$this->value);
    }
}
