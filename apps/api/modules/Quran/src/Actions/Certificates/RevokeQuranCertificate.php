<?php

namespace Modules\Quran\Actions\Certificates;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Quran\Models\QuranCertificate;
use Modules\Quran\Support\QuranArea;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Revokes a certificate, once, with the academy's reason (docs/architecture/02,
 * section 26): it stays with its number, and verifying it says it was
 * revoked (never why). Audited by its number, never the reason.
 */
final class RevokeQuranCertificate
{
    public function __construct(
        private readonly QuranArea $area,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(QuranCertificate $certificate, string $actorId, string $reason): QuranCertificate
    {
        $this->area->require();
        $reason = trim(strip_tags($reason));
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:3', 'max:300']], [], trans('quran::certificates.attributes'))->validate();

        return DB::transaction(function () use ($certificate, $actorId, $reason) {
            $certificate = QuranCertificate::query()->lockForUpdate()->findOrFail($certificate->id);
            if ($certificate->isRevoked()) {
                throw ValidationException::withMessages(['certificate' => __('quran::certificates.errors.revoked')]);
            }
            $certificate->fill(['revoked_at' => CarbonImmutable::now(), 'revoke_reason' => $reason, 'revoked_by' => $actorId])->save();
            $this->audit->record('quran.certificate_revoked', 'quran_certificate', $certificate->id, ['number' => $certificate->number]);

            return $certificate;
        });
    }
}
