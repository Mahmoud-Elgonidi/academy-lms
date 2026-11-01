<?php

namespace Modules\Quran\Actions\Certificates;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Domain\Certificates\CertificateNumber;
use Modules\Quran\Events\CertificateIssued;
use Modules\Quran\Models\QuranCertificate;
use Modules\Quran\Models\QuranExam;
use Modules\Quran\Models\QuranSettings;
use Modules\Quran\Programs\Plans;
use Modules\Quran\Support\QuranArea;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Issues the certificate a passed exam earns (docs/architecture/02, section
 * 26), while the academy gives certificates and the exam's program counts
 * for one: the academy's next number (Q-{year}-{000123}, in order, under a
 * lock), a random code to verify it by, and the portion, riwaya, grade,
 * examiner, title and signature as they are now; never changed after. One
 * an exam (a second call gives the same one). Audited by its number.
 */
final class IssueQuranCertificate
{
    public function __construct(
        private readonly QuranArea $area,
        private readonly Plans $plans,
        private readonly TenantContext $tenants,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(QuranExam $exam, ?string $actorId = null, ?CarbonImmutable $now = null): QuranCertificate
    {
        $this->area->require();
        $now ??= CarbonImmutable::now();
        $settings = QuranSettings::current();
        $exam->loadMissing('program');
        $why = match (true) {
            ! $settings->certificates_enabled => 'off',
            ! $exam->program->counts_for_certificate => 'program',
            $exam->status !== 'passed' => 'not_passed',
            default => null,
        };
        if ($why !== null) {
            throw ValidationException::withMessages(['exam' => __('quran::certificates.errors.'.$why)]);
        }

        return DB::transaction(function () use ($exam, $actorId, $now, $settings) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ['quran-certificate:'.$this->tenants->require()->id]);
            $issued = QuranCertificate::query()->where('exam_id', $exam->id)->first();
            if ($issued !== null) {
                return $issued;
            }
            $seq = (int) QuranCertificate::query()->max('seq') + 1;
            $program = $exam->program;
            $certificate = QuranCertificate::query()->create([
                'exam_id' => $exam->id, 'student_id' => $exam->student_id, 'program_id' => $program->id, 'seq' => $seq,
                'number' => CertificateNumber::format((int) $now->setTimezone($this->plans->timezone())->year, $seq),
                // 16 of 30 letters and digits (78 bits): never guessed; the database holds it unique on the platform.
                'code' => CertificateNumber::code(random_int(...)),
                'portion' => [
                    'kind' => $exam->stop_kind, 'number' => $exam->stop_number, 'program' => $program->name, 'track' => $program->track,
                    'scope' => ['from_surah' => $program->scope_from_surah, 'from_verse' => $program->scope_from_verse, 'to_surah' => $program->scope_to_surah, 'to_verse' => $program->scope_to_verse],
                ],
                'riwaya' => $settings->riwaya, 'grade' => (string) $exam->grade, 'examiner_id' => $exam->examiner_id,
                'title' => $settings->certificate_title, 'signature' => $settings->certificate_signature, 'issued_at' => $now, 'issued_by' => $actorId,
            ]);
            $this->audit->record('quran.certificate_issued', 'quran_certificate', $certificate->id, ['number' => $certificate->number, 'exam_id' => $exam->id]);
            CertificateIssued::dispatch($certificate->id);

            return $certificate;
        });
    }
}
