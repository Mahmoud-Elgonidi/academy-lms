<?php

namespace Modules\Platform\Contracts;

use Modules\Platform\Contracts\DTOs\DiscoveredAccount;

/**
 * A person's accounts across academies, for the shared app to list once
 * Identity has verified the email or number (never before). Active accounts
 * in active academies only. Implemented with the BYPASSRLS connection.
 */
interface AccountDirectory
{
    /** @return list<DiscoveredAccount> */
    public function withEmail(string $email): array;

    /** @return list<DiscoveredAccount> */
    public function withPhone(string $phoneE164): array;
}
