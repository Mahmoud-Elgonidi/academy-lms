<?php

namespace Modules\SharedKernel\Files;

/**
 * Checks an uploaded file for malware before it is kept (docs/architecture/03,
 * section 11). Every upload goes through the CleanFile rule, which asks this;
 * by default nothing is checked (NullUploadScanner), and a server that runs
 * clamd binds ClamAvScanner (UPLOAD_SCANNER=clamav).
 */
interface UploadScanner
{
    public function scan(string $path): ScanVerdict;

    /** Null when the scanner answers, else what went wrong; and what it is (its version) when it does. */
    public function ping(): ?string;

    public function version(): ?string;
}
