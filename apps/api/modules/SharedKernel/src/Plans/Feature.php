<?php

namespace Modules\SharedKernel\Plans;

/**
 * Areas an academy may have or not (docs/architecture/02, "وحدات الأكاديمية").
 * Paid ones come with the SaaS plan and only the platform team switches them
 * (saas_plans.features, the academy's features_on/off); optional ones are
 * free, in every plan at first, and the academy itself may hide them (and
 * bring them back). The rest (lessons, scheduling, families, reports and
 * homework, invoices paid by hand, live lessons, notifications, the team,
 * safeguarding, privacy) is always on. A module guards its own area: its
 * screens (canAccess), its API routes (the "plan.feature" middleware), its
 * Actions and what it sends.
 */
enum Feature: string
{
    // Paid: the plan's.
    case Crm = 'crm';
    case OnlinePayments = 'online_payments';
    case Import = 'import';
    case CustomBranding = 'custom_branding';
    case Payroll = 'payroll';
    case Ai = 'ai';
    case Quran = 'quran';

    // Optional: free, on at first; the academy may hide them.
    case Groups = 'groups';
    case Curriculum = 'curriculum';
    case Games = 'games';
    case Messaging = 'messaging';
    case Announcements = 'announcements';
    case Support = 'support';
    case Evaluation = 'evaluation';
    case Site = 'site';

    public function label(): string
    {
        return __('shared::plans.features.'.$this->value);
    }

    /** One line on what the area is, in the reader's language. */
    public function description(): string
    {
        return __('shared::plans.descriptions.'.$this->value);
    }

    /** paid (the plan's, switched by the platform team) | optional (free, the academy may hide it) */
    public function kind(): string
    {
        return in_array($this, self::optional(), true) ? 'optional' : 'paid';
    }

    public function isOptional(): bool
    {
        return $this->kind() === 'optional';
    }

    /** @return list<self> the free areas an academy may hide, in the order they are shown */
    public static function optional(): array
    {
        return [self::Groups, self::Curriculum, self::Games, self::Messaging, self::Announcements, self::Support, self::Evaluation, self::Site];
    }

    /** @return list<self> the plan's areas */
    public static function paid(): array
    {
        return array_values(array_filter(self::cases(), fn (self $f) => ! $f->isOptional()));
    }

    /** @return list<self> what must be on for this one to be on (games play on the curriculum's lessons) */
    public function dependsOn(): array
    {
        return match ($this) {
            self::Games => [self::Curriculum],
            default => [],
        };
    }

    /** @return list<self> what goes off with this one */
    public function dependents(): array
    {
        return array_values(array_filter(self::cases(), fn (self $f) => in_array($this, $f->dependsOn(), true)));
    }
}
