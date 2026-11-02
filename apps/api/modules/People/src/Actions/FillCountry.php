<?php

namespace Modules\People\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Enums\Role;
use Modules\People\Models\GuardianProfile;
use Modules\People\Models\StudentProfile;

/**
 * Gives a person the country they were known by elsewhere (a lead's), only
 * where they have none yet: on their student details when they study here,
 * and as a guardian when they are one. A country someone already has (typed
 * by the office, put right by the family) is never changed, and nor is one
 * cleared later: this runs when an account is prepared, or once to catch up.
 *
 * @return int how many profiles took it
 */
final class FillCountry
{
    public function handle(string $userId, ?string $country): int
    {
        $country = $country === null ? '' : strtoupper(trim($country));
        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            return 0;
        }
        $roles = DB::table('user_roles')->where('user_id', $userId)->pluck('role')->all();
        $filled = 0;

        if (in_array(Role::Student->value, $roles, true)) {
            $profile = StudentProfile::query()->find($userId);
            if ($profile === null) {
                StudentProfile::query()->create(['user_id' => $userId, 'country' => $country]);
                $filled++;
            } elseif ($profile->country === null) {
                $profile->forceFill(['country' => $country])->save();
                $filled++;
            }
        }
        if (in_array(Role::Guardian->value, $roles, true)) {
            $profile = GuardianProfile::query()->find($userId);
            if ($profile === null) {
                GuardianProfile::query()->create(['user_id' => $userId, 'country' => $country]);
                $filled++;
            } elseif ($profile->country === null) {
                $profile->forceFill(['country' => $country])->save();
                $filled++;
            }
        }

        return $filled;
    }
}
