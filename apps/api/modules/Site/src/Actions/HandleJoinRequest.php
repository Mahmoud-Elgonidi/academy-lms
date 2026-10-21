<?php

namespace Modules\Site\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\Site\Models\JoinRequest;
use Modules\Site\Support\RequestAccess;

/**
 * Someone at the academy answers a request from its page: talked to them,
 * accepted or turned down, with a note for the academy (never shown to
 * whoever asked). A trial is the sales team's, a teacher asking to teach is
 * who runs the teachers' (Support\RequestAccess). Audited with the answer only.
 */
final class HandleJoinRequest
{
    public const ANSWERS = ['contacted', 'accepted', 'declined'];

    public function __construct(private readonly AuditTrail $audit, private readonly RequestAccess $access) {}

    /** @throws ValidationException */
    public function handle(string $id, string $status, ?string $note = null): JoinRequest
    {
        $note = filled($note) ? trim((string) $note) : null;
        Validator::make(['status' => $status, 'note' => $note], [
            'status' => ['required', Rule::in(self::ANSWERS)],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['note' => __('site::site.requests.note')])->validate();

        return DB::transaction(function () use ($id, $status, $note) {
            $request = JoinRequest::query()->lockForUpdate()->whereKey(Str::isUuid($id) ? $id : null)->firstOrFail();
            if (! $this->access->may($request->kind)) {
                throw new AuthorizationException(__('site::site.errors.cannot_handle'));
            }
            $request->fill([
                'status' => $status,
                'note' => $note ?? $request->note,
                'handled_by' => auth()->id(),
                'handled_at' => now(),
            ])->save();
            $this->audit->record('site.request_handled', 'site_request', $request->id, ['request_status' => $status]);

            return $request;
        });
    }
}
