<?php

namespace Modules\Quran\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A certificate was issued for a passed exam (the family hears of it, with the page). Raised after commit. */
final class CertificateIssued implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly string $certificateId) {}
}
