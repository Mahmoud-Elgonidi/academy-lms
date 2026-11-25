<?php

namespace Modules\Scheduling\Contracts;

/**
 * Whom the academy's free trials speak to, for the words of the booked
 * trial's message: an academy whose page is for adults (it trains
 * institutions and professionals) says a session and a trainer, not a
 * lesson and a teacher (`scheduling.trial_booked_adults`). The academy's page
 * is the CRM's to read (Site sits on top), so the CRM binds its own; until
 * then every trial speaks to families as before.
 */
interface TrialAudience
{
    public function forAdults(): bool;
}
