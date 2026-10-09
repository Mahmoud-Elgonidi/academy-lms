<?php

namespace Modules\SharedKernel\Files;

/** No scanner on this server: every file passes (the type and size rules still apply). */
final class NullUploadScanner implements UploadScanner
{
    public function scan(string $path): ScanVerdict
    {
        return ScanVerdict::clean();
    }

    public function ping(): ?string
    {
        return null;
    }

    public function version(): ?string
    {
        return null;
    }
}
