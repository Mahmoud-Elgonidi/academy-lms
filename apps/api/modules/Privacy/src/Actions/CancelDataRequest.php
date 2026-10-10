<?php

namespace Modules\Privacy\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Privacy\Models\DataRequest;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Taking an erasure back while it waits, or in the day after the academy
 * approved it, or a correction while it waits: by whoever asked, the person
 * themselves, or the academy.
 */
final class CancelDataRequest
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(DataRequest $request, string $actorId): DataRequest
    {
        if (! in_array($actorId, [$request->requested_by, $request->subject_id], true) && ! Gate::allows(Ability::HandleDataRequests->value)) {
            throw new AuthorizationException(__('privacy::requests.errors.not_yours'));
        }

        return DB::transaction(function () use ($request) {
            $request = DataRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (! in_array($request->kind, DataRequest::ANSWERED, true) || ! $request->isOpen()) {
                throw ValidationException::withMessages(['request' => __('privacy::requests.errors.not_open')]);
            }
            $request->forceFill(['status' => 'cancelled', 'erase_after' => null])->save();
            $this->audit->record("privacy.{$request->kind}_cancelled", 'user', $request->subject_id, ['request' => $request->id]);

            return $request;
        });
    }
}
