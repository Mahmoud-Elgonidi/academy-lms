<?php

namespace Modules\Privacy\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Privacy\Contracts\Events\ErasureRequested;
use Modules\Privacy\Models\DataRequest;
use Modules\Privacy\PrivacyRules;
use Modules\Privacy\Subjects;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Asking for an account to be erased: the person, their guardian (for a
 * child), or the academy writing it down for them. The academy answers
 * within 30 days (it may need to settle lessons or money first) and hears
 * of it now. Asked again while open, it is the same request.
 */
final class RequestErasure
{
    public const REASON_MAX = 1000;

    public function __construct(
        private readonly PrivacyRules $rules,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(string $subjectId, string $requesterId, string $side, ?string $reason = null): DataRequest
    {
        if (! $this->rules->canAsk($requesterId, $subjectId, $side)) {
            throw new AuthorizationException(__('privacy::requests.errors.not_yours'));
        }
        Subjects::ensureNotErased($subjectId);
        $reason = trim((string) $reason) === '' ? null : trim((string) $reason);
        if ($reason !== null && mb_strlen($reason) > self::REASON_MAX) {
            throw ValidationException::withMessages(['reason' => __('validation.max.string', ['attribute' => __('privacy::requests.reason'), 'max' => self::REASON_MAX])]);
        }

        return DB::transaction(function () use ($subjectId, $requesterId, $side, $reason) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["privacy-erasure:{$subjectId}"]);
            $open = DataRequest::query()->where('subject_id', $subjectId)->where('kind', DataRequest::ERASURE)->whereIn('status', DataRequest::OPEN)->first();
            if ($open !== null) {
                return $open;
            }

            $request = DataRequest::query()->create([
                'kind' => DataRequest::ERASURE, 'subject_id' => $subjectId, 'requested_by' => $requesterId,
                'reason' => $reason, 'due_at' => now()->addDays(DataRequest::ANSWER_DAYS),
            ]);
            $this->audit->record('privacy.erasure_requested', 'user', $subjectId, ['request' => $request->id, 'side' => $side]);
            DB::afterCommit(fn () => event(new ErasureRequested($request->id)));

            return $request;
        });
    }
}
