<?php

namespace Modules\Site\Privacy;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Privacy\PersonalDataPart;
use Modules\SharedKernel\Privacy\PersonalDataSource;

/**
 * What a person asked from the academy's page before (or since) they had an
 * account here: the requests sent with their account's number or email.
 * Erasing removes them; nothing holds an erasure back (a trial's lead is
 * the CRM's, on its own terms).
 */
final class JoinRequestsData implements PersonalDataSource
{
    public function key(): string
    {
        return 'site_requests';
    }

    public function export(string $userId): PersonalDataPart
    {
        $rows = $this->theirs($userId)?->orderBy('created_at')
            ->get(['kind', 'name', 'phone', 'email', 'country', 'timezone', 'locale', 'for_child', 'child_name', 'child_birth_year', 'years_teaching', 'message', 'status', 'created_at']);

        return new PersonalDataPart(['requests' => $rows === null ? [] : $rows->map(fn (object $r) => (array) $r)->all()]);
    }

    public function blockers(string $userId): array
    {
        return [];
    }

    public function erase(string $userId): array
    {
        return ['site_requests' => (int) $this->theirs($userId)?->delete()];
    }

    /** The requests sent with this account's number or email; null when it has neither. */
    private function theirs(string $userId): ?Builder
    {
        $user = DB::table('users')->where('id', $userId)->first(['email', 'phone_e164']);
        if ($user === null || ($user->email === null && $user->phone_e164 === null)) {
            return null;
        }

        return DB::table('site_requests')->where(function ($q) use ($user) {
            if ($user->phone_e164 !== null) {
                $q->orWhere('phone', $user->phone_e164);
            }
            if ($user->email !== null) {
                $q->orWhere('email', $user->email);
            }
        });
    }
}
