<?php

namespace Modules\Billing\Contracts;

/**
 * What a coupon is for (beside its kind, a percent or an amount): a code the
 * academy's staff give out (general), or a personal offer the academy's own
 * rules make for one payer after a free trial (trial_offer: once, the first
 * invoice of a course, until a moment; docs/architecture/02, section 27).
 */
enum CouponPurpose: string
{
    case General = 'general';
    case TrialOffer = 'trial_offer';
}
