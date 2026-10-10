<?php

namespace Modules\Privacy\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Privacy\Contracts\Events\CorrectionRequested;
use Modules\Privacy\Models\DataRequest;
use Modules\Privacy\PrivacyRules;
use Modules\Privacy\Subjects;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Asking the academy to put right what someone cannot correct on their own
 * (a birth date, a name on an invoice, something written in a report): in
 * their own words, by the person, their guardian, or the academy writing it
 * down for them. The academy answers within 30 days and hears of it now. One
 * waits at a time: while it does, what else is wrong goes into that one's
 * conversation with the academy.
 */
final class RequestCorrection
{
    public const SAYS_MIN = 10;

    public const SAYS_MAX = 1000;

    public function __construct(
        private readonly PrivacyRules $rules,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(string $subjectId, string $requesterId, string $side, string $says): DataRequest
    {
        if (! $this->rules->canAsk($requesterId, $subjectId, $side)) {
            throw new AuthorizationException(__('privacy::requests.errors.not_yours'));
        }
        Subjects::ensureNotErased($subjectId);
        $says = trim($says);
        if (mb_strlen($says) < self::SAYS_MIN || mb_strlen($says) > self::SAYS_MAX) {
            throw ValidationException::withMessages(['says' => __('privacy::requests.errors.says', ['min' => self::SAYS_MIN, 'max' => self::SAYS_MAX])]);
        }

        return DB::transaction(function () use ($subjectId, $requesterId, $side, $says) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["privacy-correction:{$subjectId}"]);
            if (DataRequest::query()->where('subject_id', $subjectId)->where('kind', DataRequest::CORRECTION)->whereIn('status', DataRequest::OPEN)->exists()) {
                throw ValidationException::withMessages(['says' => __('privacy::requests.errors.correction_open')]);
            }

            $request = DataRequest::query()->create([
                'kind' => DataRequest::CORRECTION, 'subject_id' => $subjectId, 'requested_by' => $requesterId,
                'reason' => $says, 'due_at' => now()->addDays(DataRequest::ANSWER_DAYS),
            ]);
            // What they wrote stays on the request; the audit says who asked about whom, not the words.
            $this->audit->record('privacy.correction_requested', 'user', $subjectId, ['request' => $request->id, 'side' => $side]);
            DB::afterCommit(fn () => event(new CorrectionRequested($request->id)));

            return $request;
        });
    }
}
