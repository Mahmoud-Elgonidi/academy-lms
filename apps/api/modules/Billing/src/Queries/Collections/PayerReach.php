<?php

namespace Modules\Billing\Queries\Collections;

use Illuminate\Support\Facades\DB;
use Modules\People\Contracts\PeopleApi;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Who hears about a payer's money and on whose clock (docs/architecture/02,
 * section 27, J5a): the payer's own account (a guardian, or an adult learner
 * paying for themselves; never a second guardian who does not pay), and
 * never a child's own account: a payer who is a minor student is reached
 * through their guardians. A payer always has an account (billing_accounts),
 * made by the office or the CRM even when they never signed in: their
 * messages go by WhatsApp and email at its number and address all the same.
 */
final class PayerReach
{
    public function __construct(private readonly PeopleApi $people, private readonly TenantContext $tenant) {}

    /** @return list<string> */
    public function recipients(string $payerId): array
    {
        return $this->people->isMinor($payerId) ? $this->people->guardiansOf($payerId) : [$payerId];
    }

    /** The payer's IANA zone (their account's, else the academy's): their days and hours. */
    public function timezone(string $payerId): string
    {
        $zone = DB::table('users')->where('id', $payerId)->value('timezone');

        return is_string($zone) && $zone !== '' ? $zone : $this->tenant->require()->defaultTimezone;
    }

    /** @return 'ar'|'en' */
    public function locale(string $payerId): string
    {
        return DB::table('users')->where('id', $payerId)->value('locale') === 'en' ? 'en' : 'ar';
    }
}
