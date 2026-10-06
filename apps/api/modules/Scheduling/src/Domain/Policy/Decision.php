<?php

namespace Modules\Scheduling\Domain\Policy;

/**
 * What the policy decided: whether it may happen, what it does to the
 * student's credit (consumed | forfeit | refund | makeup | keep | none), the
 * teacher's pay for the session, and the performance event it records.
 */
final readonly class Decision
{
    public const CREDIT_EFFECTS = ['consumed', 'forfeit', 'refund', 'makeup', 'keep', 'none'];

    public function __construct(
        public int $rule,
        public bool $allowed = true,
        public string $credit = 'none',
        public int $teacherPayPct = 0,
        public ?string $kpi = null,
        public ?string $kpiSeverity = null,
        public bool $countsQuota = false,
        /** Why it is not allowed (a translation key), when it is not. */
        public ?string $refusal = null,
    ) {}

    public static function refuse(int $rule, string $why): self
    {
        return new self($rule, allowed: false, refusal: $why);
    }

    /** The same decision with the credit given back: a manager's exception. */
    public function waived(): self
    {
        return new self($this->rule, $this->allowed, $this->credit === 'forfeit' ? 'refund' : $this->credit, $this->teacherPayPct, $this->kpi, $this->kpiSeverity, $this->countsQuota, $this->refusal);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'rule' => $this->rule,
            'credit' => $this->credit,
            'teacher_pay_pct' => $this->teacherPayPct,
            'kpi' => $this->kpi,
            'kpi_severity' => $this->kpiSeverity,
            'counts_quota' => $this->countsQuota ?: null,
        ], fn ($v) => $v !== null);
    }
}
