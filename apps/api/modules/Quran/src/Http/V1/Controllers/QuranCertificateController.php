<?php

namespace Modules\Quran\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\People\Contracts\FamilyView;
use Modules\Quran\Certificates\Certificates;
use Modules\Quran\Domain\Certificates\CertificateNumber;
use Modules\Quran\Http\V1\Resources\ExamPresenter;
use Modules\Quran\Models\QuranCertificate;
use Modules\Quran\Models\QuranSettings;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Quran certificates (docs/architecture/02, section 26): a learner's, for
 * the student and their guardians while the academy shows them to families;
 * and the public verification by a certificate's code.
 */
final class QuranCertificateController
{
    /**
     * A learner's Quran certificates.
     *
     * shown is false (and certificates empty) while the academy keeps certificates from families. Each certificate:
     * its number, title, portion, riwaya, grade, examiner and day, whether it was revoked, and while it stands its
     * page (signed for this reader) and its verification code.
     */
    public function family(Request $request, FamilyView $family, ExamPresenter $present): JsonResponse
    {
        $request->validate(['learner_id' => ['nullable', 'string', 'max:64']]);
        $viewer = (string) $request->user()?->getAuthIdentifier();
        $asked = $request->query('learner_id', $request->input('learner_id'));
        $learner = $family->learner($viewer, is_string($asked) && $asked !== '' ? $asked : null)?->id ?? throw new NotFoundHttpException;
        $shown = QuranSettings::current()->certificates_family;
        $certificates = $shown ? QuranCertificate::query()->where('student_id', $learner)->orderByDesc('issued_at')->get() : collect();

        return response()->json(['data' => [
            'learner_id' => $learner,
            'shown' => $shown,
            'certificates' => $certificates->map(fn (QuranCertificate $c) => $present->certificate($c, $viewer))->all(),
        ]]);
    }

    /**
     * Verify a Quran certificate.
     *
     * Anyone, by the code printed on it (any case, with or without dashes), on the academy's address: the
     * certificate's number, title, portion, riwaya, grade and day, the academy's name, the student's first name and
     * last initial, and whether it was revoked. 404 for a code this academy never issued.
     */
    public function verify(string $code, Certificates $certificates): JsonResponse
    {
        $code = CertificateNumber::normalize($code);
        $certificate = $code === null ? null : QuranCertificate::query()->where('code', $code)->first();
        if ($certificate === null) {
            throw new NotFoundHttpException;
        }

        return response()->json(['data' => $certificates->public($certificate)]);
    }
}
