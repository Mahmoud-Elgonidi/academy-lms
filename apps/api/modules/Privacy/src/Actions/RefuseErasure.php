<?php

namespace Modules\Privacy\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Privacy\Contracts\Events\ErasureRefused;
use Modules\Privacy\Models\DataRequest;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;

/**
 * The academy refuses an erasure, with the reason whoever asked reads (a
 * law that keeps the data, money still owed, a dispute still open).
 */
final class RefuseErasure
{
    public const ANSWER_MAX = 1000;

    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(DataRequest $request, string $actorId, string $answer): DataRequest
    {
        if (! Gate::allows(Ability::HandleDataRequests->value)) {
            throw new AuthorizationException;
        }
        $answer = trim($answer);
        if ($answer === '' || mb_strlen($answer) > self::ANSWER_MAX) {
            throw ValidationException::withMessages(['answer' => __('privacy::requests.errors.answer')]);
        }

        return DB::transaction(function () use ($request, $actorId, $answer) {
            $request = DataRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->kind !== DataRequest::ERASURE || $request->status !== 'pending') {
                throw ValidationException::withMessages(['request' => __('privacy::requests.errors.not_open')]);
            }
            $request->forceFill(['status' => 'refused', 'answer' => $answer, 'decided_by' => $actorId, 'decided_at' => now()])->save();
            $this->audit->record('privacy.erasure_refused', 'user', $request->subject_id, ['request' => $request->id, 'answer' => $answer]);
            DB::afterCommit(fn () => event(new ErasureRefused($request->id)));

            return $request;
        });
    }
}
