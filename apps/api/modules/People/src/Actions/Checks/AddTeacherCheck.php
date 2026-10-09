<?php

namespace Modules\People\Actions\Checks;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\People\Checks\Events\TeacherCheckSubmitted;
use Modules\People\Contracts\CheckKind;
use Modules\People\Models\TeacherCheck;
use Modules\People\Models\TeacherProfile;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Files\CleanFile;
use Modules\SharedKernel\Tenancy\TenantContext;
use Throwable;

/**
 * A teacher sends a document their vetting rests on (a criminal record check,
 * an ID, a qualification), or someone at the academy adds one for them and,
 * having seen the original, may verify it at once. The scan goes on the
 * private disk under the academy's folder; one of a kind waits at a time.
 * In the activity log.
 */
final class AddTeacherCheck
{
    public function __construct(
        private readonly AuditTrail $audit,
        private readonly TenantContext $tenants,
        private readonly DecideTeacherCheck $decide,
    ) {}

    public function handle(
        string $teacherId,
        string $kind,
        ?string $reference,
        ?CarbonImmutable $issuedOn,
        ?CarbonImmutable $expiresOn,
        UploadedFile $file,
        string $actorId,
        bool $verifyNow = false,
    ): TeacherCheck {
        $kind = CheckKind::tryFrom($kind) ?? throw ValidationException::withMessages(['kind' => __('people::checks.errors.kind')]);
        Validator::make(
            ['file' => $file, 'reference' => $reference],
            [
                'file' => ['required', 'file', 'max:'.TeacherCheck::MAX_KB, 'mimetypes:'.implode(',', TeacherCheck::MIMES), new CleanFile],
                'reference' => ['nullable', 'string', 'max:120'],
            ],
            [],
            ['file' => __('people::checks.fields.file'), 'reference' => __('people::checks.fields.reference')],
        )->validate();
        if ($issuedOn !== null && $issuedOn->toDateString() > CarbonImmutable::now()->addDay()->toDateString()) {
            throw ValidationException::withMessages(['issued_on' => __('people::checks.errors.issued_future')]);
        }
        if ($issuedOn !== null && $expiresOn !== null && $expiresOn->toDateString() < $issuedOn->toDateString()) {
            throw ValidationException::withMessages(['expires_on' => __('people::checks.errors.expires_before')]);
        }
        if (! TeacherProfile::query()->whereKey($teacherId)->exists()) {
            throw ValidationException::withMessages(['teacher' => __('people::checks.errors.not_teacher')]);
        }

        $academyId = $this->tenants->require()->id;
        $path = $file->storeAs("academies/{$academyId}/teacher-checks/{$teacherId}", Str::uuid7().'.'.($file->guessExtension() ?: 'bin'), ['disk' => TeacherCheck::DISK]);

        try {
            $check = DB::transaction(function () use ($teacherId, $kind, $reference, $issuedOn, $expiresOn, $file, $path, $actorId, $verifyNow) {
                DB::select('select pg_advisory_xact_lock(hashtext(?))', ['teacher-checks:'.$teacherId]);
                if (TeacherCheck::query()->where('teacher_id', $teacherId)->where('kind', $kind->value)->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages(['kind' => __('people::checks.errors.one_waiting')]);
                }
                $check = TeacherCheck::query()->create([
                    'teacher_id' => $teacherId, 'kind' => $kind, 'reference' => trim((string) $reference) ?: null,
                    'issued_on' => $issuedOn, 'expires_on' => $expiresOn,
                    'file_path' => $path, 'file_name' => Str::limit($file->getClientOriginalName(), 180, ''),
                    'mime' => (string) $file->getMimeType(), 'size' => (int) $file->getSize(),
                    'status' => 'pending', 'uploaded_by' => $actorId,
                ]);
                $this->audit->record('people.check_added', 'user', $teacherId, [
                    'teacher' => (string) DB::table('users')->where('id', $teacherId)->value('name'), 'document' => $kind->value,
                ]);
                if ($verifyNow) {
                    return $this->decide->handle($check->id, true, $expiresOn, null, $actorId);
                }
                DB::afterCommit(fn () => event(new TeacherCheckSubmitted($check->academy_id, $check->id)));

                return $check;
            });
        } catch (Throwable $e) {
            Storage::disk(TeacherCheck::DISK)->delete($path);

            throw $e;
        }

        return $check;
    }
}
