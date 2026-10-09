<?php

namespace Modules\Privacy\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Privacy\Jobs\BuildDataExportJob;
use Modules\Privacy\Models\DataRequest;
use Modules\Privacy\PrivacyRules;
use Modules\Privacy\Subjects;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * A copy of everything the academy holds about a person (access and
 * portability): asked by them, their guardian, or the academy for them,
 * and built on its own in the background. Asked again while one is being
 * built, it is the same one; at most one a day.
 */
final class RequestDataExport
{
    public function __construct(
        private readonly PrivacyRules $rules,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(string $subjectId, string $requesterId, string $side): DataRequest
    {
        if (! $this->rules->canAsk($requesterId, $subjectId, $side)) {
            throw new AuthorizationException(__('privacy::requests.errors.not_yours'));
        }
        Subjects::ensureNotErased($subjectId);

        return DB::transaction(function () use ($subjectId, $requesterId, $side) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["privacy-export:{$subjectId}"]);
            $building = DataRequest::query()->where('subject_id', $subjectId)->where('kind', DataRequest::EXPORT)->where('status', 'pending')->first();
            if ($building !== null) {
                return $building;
            }
            $recent = DataRequest::query()->where('subject_id', $subjectId)->where('kind', DataRequest::EXPORT)->where('status', 'ready')
                ->where('created_at', '>', now()->subHours(DataRequest::EXPORT_EVERY_HOURS))->exists();
            if ($recent && $side !== 'staff') {
                throw ValidationException::withMessages(['kind' => __('privacy::requests.errors.export_recent')]);
            }

            $request = DataRequest::query()->create(['kind' => DataRequest::EXPORT, 'subject_id' => $subjectId, 'requested_by' => $requesterId]);
            $this->audit->record('privacy.export_requested', 'user', $subjectId, ['request' => $request->id, 'side' => $side]);
            DB::afterCommit(fn () => BuildDataExportJob::dispatch($request->id));

            return $request;
        });
    }
}
