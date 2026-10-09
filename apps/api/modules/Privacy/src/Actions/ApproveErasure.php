<?php

namespace Modules\Privacy\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Privacy\Contracts\Events\ErasureApproved;
use Modules\Privacy\Models\DataRequest;
use Modules\Privacy\Queries\ErasurePreview;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;

/**
 * The academy approves an erasure once nothing holds it back (lessons to
 * come, courses running, money due, a child left without a guardian). The
 * account goes a day later (privacy:sweep): whoever asked and the person
 * hear of it while their addresses still exist, and it can still be taken
 * back until then.
 */
final class ApproveErasure
{
    public function __construct(
        private readonly ErasurePreview $preview,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(DataRequest $request, string $actorId): DataRequest
    {
        if (! Gate::allows(Ability::HandleDataRequests->value)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($request, $actorId) {
            $request = DataRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->kind !== DataRequest::ERASURE || $request->status !== 'pending') {
                throw ValidationException::withMessages(['request' => __('privacy::requests.errors.not_open')]);
            }
            $blockers = $this->preview->blockers($request->subject_id);
            if ($blockers !== []) {
                throw ValidationException::withMessages(['request' => implode(' ', $blockers)]);
            }
            $request->forceFill(['status' => 'approved', 'decided_by' => $actorId, 'decided_at' => now(), 'erase_after' => now()->addHours(DataRequest::GRACE_HOURS)])->save();
            $this->audit->record('privacy.erasure_approved', 'user', $request->subject_id, ['request' => $request->id]);
            DB::afterCommit(fn () => event(new ErasureApproved($request->id)));

            return $request;
        });
    }
}
