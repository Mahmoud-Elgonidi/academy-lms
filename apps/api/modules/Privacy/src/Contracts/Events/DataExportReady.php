<?php

namespace Modules\Privacy\Contracts\Events;

/** A person's copy of their data is ready to download. */
final readonly class DataExportReady
{
    public function __construct(public string $requestId) {}
}
