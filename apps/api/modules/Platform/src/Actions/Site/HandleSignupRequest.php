<?php

namespace Modules\Platform\Actions\Site;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\Site\SignupRequest;

/**
 * The platform team answers a signup request: talked to them, turned it down
 * (with a note for the team), or made the academy (the console's New academy
 * page calls this once it is made). Audited with the answer only.
 */
final class HandleSignupRequest
{
    public const ANSWERS = ['contacted', 'declined', 'created'];

    public function __construct(private readonly PlatformAudit $audit) {}

    /** @throws ValidationException */
    public function handle(PlatformAdmin $admin, string $requestId, string $status, ?string $note = null, ?string $academyId = null): SignupRequest
    {
        if (! $admin->is_active) {
            throw new AuthorizationException;
        }
        $note = filled($note) ? trim((string) $note) : null;
        Validator::make(['status' => $status, 'note' => $note, 'academy' => $academyId], [
            'status' => ['required', Rule::in(self::ANSWERS)],
            'note' => ['nullable', 'string', 'max:500'],
            'academy' => [Rule::requiredIf($status === 'created'), 'nullable', 'uuid'],
        ])->validate();

        return DB::connection('pgsql_platform')->transaction(function () use ($admin, $requestId, $status, $note, $academyId) {
            $request = SignupRequest::query()->lockForUpdate()->findOrFail($requestId);
            $request->fill([
                'status' => $status,
                'note' => $note ?? $request->note,
                'academy_id' => $status === 'created' ? $academyId : $request->academy_id,
                'handled_by' => $admin->id,
                'handled_at' => now(),
            ])->save();
            $this->audit->record($status === 'created' ? $academyId : null, 'platform.signup_request_handled', 'signup_request', $request->id, ['signup' => $status]);

            return $request;
        });
    }
}
