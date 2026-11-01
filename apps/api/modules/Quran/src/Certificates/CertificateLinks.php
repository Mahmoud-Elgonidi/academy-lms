<?php

namespace Modules\Quran\Certificates;

use Illuminate\Support\Facades\URL;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\Quran\Domain\Certificates\CertificateNumber;
use Modules\Quran\Models\QuranCertificate;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Where a certificate is read: its page, signed per reader on the academy's
 * host (relative signature, PAGE_DAYS), and the public page that verifies
 * it by its code (the portal's /{locale}/certificates/{code}).
 */
final class CertificateLinks
{
    public const PAGE_DAYS = 30;

    public function __construct(
        private readonly TenantDirectory $directory,
        private readonly TenantContext $tenants,
    ) {}

    public function page(QuranCertificate $certificate, ?string $readerId = null): string
    {
        $path = URL::temporarySignedRoute('quran.certificate', now()->addDays(self::PAGE_DAYS), array_filter(['certificate' => $certificate->id, 'u' => $readerId]), absolute: false);

        return $this->base().$path;
    }

    public function verify(QuranCertificate $certificate, ?string $locale = null): string
    {
        return $this->base().'/'.($locale ?? app()->getLocale()).'/certificates/'.CertificateNumber::display($certificate->code);
    }

    private function base(): string
    {
        return rtrim($this->directory->baseUrl($this->tenants->require()), '/');
    }
}
