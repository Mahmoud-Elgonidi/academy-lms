<?php

namespace Modules\Privacy\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Privacy\Contracts\Events\CorrectionAnswered;
use Modules\Privacy\Models\DataRequest;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;

/**
 * The academy answers a correction: done (it put the data right where it
 * lives: the person's page, an invoice through a credit note, a report; an
 * optional note says what changed) or refused (the data was right, or the
 * law keeps it as it was; the reason is required). Whoever asked reads it.
 */
final class AnswerCorrection
{
    public const ANSWER_MAX = 1000;

    public function __construct(private readonly AuditTrail $audit) {}

    public function done(DataRequest $request, string $actorId, ?string $note = null): DataRequest
    {
        $note = trim((string) $note);

        return $this->answer($request, $actorId, 'done', $note === '' ? null : $note);
    }

    public function refuse(DataRequest $request, string $actorId, string $answer): DataRequest
    {
        $answer = trim($answer);
        if ($answer === '') {
            throw ValidationException::withMessages(['answer' => __('privacy::requests.errors.answer')]);
        }

        return $this->answer($request, $actorId, 'refused', $answer);
    }

    private function answer(DataRequest $request, string $actorId, string $status, ?string $answer): DataRequest
    {
        if (! Gate::allows(Ability::HandleDataRequests->value)) {
            throw new AuthorizationException;
        }
        if ($answer !== null && mb_strlen($answer) > self::ANSWER_MAX) {
            throw ValidationException::withMessages(['answer' => __('validation.max.string', ['attribute' => __('privacy::requests.answer'), 'max' => self::ANSWER_MAX])]);
        }

        return DB::transaction(function () use ($request, $actorId, $status, $answer) {
            $request = DataRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->kind !== DataRequest::CORRECTION || $request->status !== 'pending') {
                throw ValidationException::withMessages(['request' => __('privacy::requests.errors.not_open')]);
            }
            $request->forceFill([
                'status' => $status, 'answer' => $answer, 'decided_by' => $actorId, 'decided_at' => now(),
                'done_at' => $status === 'done' ? now() : null,
            ])->save();
            $this->audit->record("privacy.correction_{$status}", 'user', $request->subject_id, ['request' => $request->id]);
            DB::afterCommit(fn () => event(new CorrectionAnswered($request->id)));

            return $request;
        });
    }
}
