<?php

namespace Modules\Quran\Http\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Quran\Certificates\CertificateLinks;
use Modules\Quran\Certificates\CertificateQr;
use Modules\Quran\Certificates\Certificates;
use Modules\Quran\Domain\Certificates\CertificateNumber;
use Modules\Quran\Models\QuranCertificate;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Time\ViewerClock;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A certificate's own page on the academy's host (docs/architecture/02,
 * section 26), by a signed link (the family's message, the portal and the
 * app): to read and print, in the academy's name, logo and colour, with a
 * QR code to its public verification. In the reader's language when the
 * link names them.
 */
final class CertificatePageController
{
    public function show(Request $request, string $certificate, Certificates $certificates, CertificateLinks $links, BrandingApi $branding, TenantContext $tenants): View
    {
        $found = Str::isUuid($certificate) ? QuranCertificate::query()->find($certificate) : null;
        if ($found === null) {
            throw new NotFoundHttpException;
        }
        $reader = is_string($request->query('u')) && Str::isUuid($request->query('u'))
            ? DB::table('users')->where('id', $request->query('u'))->first(['locale', 'time_format']) : null;
        app()->setLocale($reader?->locale ?: $tenants->require()->defaultLocale);
        app(ViewerClock::class)->choose($reader?->time_format);
        $timezone = $tenants->require()->defaultTimezone;
        $verify = $links->verify($found);

        return view('quran::certificates.page', [
            'c' => $found,
            'title' => $certificates->title($found),
            'student' => $certificates->name($found->student_id),
            'portion' => $certificates->portion($found),
            'program' => (string) ($found->portion['program'] ?? ''),
            'riwaya' => __('quran::settings.riwayat.'.$found->riwaya),
            'grade' => __('quran::programs.grades.'.$found->grade),
            'examiner' => $certificates->name($found->examiner_id),
            'issued' => $found->issued_at->setTimezone($timezone)->locale(app()->getLocale())->translatedFormat('j F Y'),
            'revoked' => $found->revoked_at?->setTimezone($timezone)->locale(app()->getLocale())->translatedFormat('j F Y'),
            'code' => CertificateNumber::display($found->code),
            'verify' => $verify,
            'qr' => $found->isRevoked() ? null : CertificateQr::svg($verify),
            'branding' => $branding->current(),
        ]);
    }
}
