<?php

namespace Modules\SharedKernel\Files;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\File;

/**
 * The rule every upload carries next to its type and size: the file is
 * checked by the server's UploadScanner. Infected, it is refused (and the
 * platform's health page counts it); with a scanner that does not answer,
 * refused too unless UPLOAD_SCAN_REQUIRED is off.
 */
final class CleanFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $path = match (true) {
            $value instanceof File => $value->getRealPath(),
            is_string($value) && is_file($value) => $value,
            default => null,
        };
        if (! is_string($path) || $path === '') {
            return;
        }

        $verdict = app(UploadScanner::class)->scan($path);
        if ($verdict->status === ScanVerdict::CLEAN) {
            ScannerHealth::scanned();

            return;
        }
        if ($verdict->status === ScanVerdict::INFECTED) {
            ScannerHealth::infected();
            Log::warning('uploads: a file was refused by the scanner', ['field' => $attribute, 'signature' => $verdict->signature]);
            $fail(__('shared::uploads.infected'));

            return;
        }
        ScannerHealth::failed((string) $verdict->error);
        if (config('uploads.required', true)) {
            $fail(__('shared::uploads.unavailable'));
        }
    }
}
